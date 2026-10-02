<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Group;
use App\Models\User;
use App\Services\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExpensesTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;

    private User $owner;

    private User $member;

    private User $third;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->group = Group::factory()->create();
        $this->owner = User::findOrFail($this->group->created_by);
        $this->member = User::factory()->guest()->create(['name' => 'Ali']);
        $this->third = User::factory()->guest()->create(['name' => 'Abu']);
        foreach ([$this->member, $this->third] as $user) {
            $this->group->memberships()->create(['user_id' => $user->id, 'role' => 'member']);
        }
        $this->path = '/api/groups/'.$this->group->id;
        $this->actingAs($this->owner);
    }

    private function payload(array $replace = []): array
    {
        return array_replace(['description' => 'Dinner', 'amount' => '120.00', 'paid_by' => $this->owner->id, 'expense_date' => '2026-10-02', 'notes' => null, 'split_type' => 'equal', 'participant_ids' => [$this->owner->id, $this->member->id, $this->third->id]], $replace);
    }

    private function create(array $replace = []): array
    {
        return $this->postJson($this->path.'/expenses', $this->payload($replace))->assertCreated()->json('data');
    }

    public function test_corrected_fixture_contract_and_summary(): void
    {
        $expense = $this->create();
        $this->create(['description' => 'Grab', 'amount' => '60.00', 'paid_by' => $this->member->id]);
        $this->create(['description' => 'Drinks', 'amount' => '30.00', 'paid_by' => $this->third->id]);
        $this->getJson($this->path.'/expenses/'.$expense['id'])->assertOk()->assertJsonPath('data.amount', '120.00')->assertJsonPath('data.created_by', $this->owner->id)->assertJsonPath('data.expense_date', '2026-10-02')->assertJsonStructure(['data' => ['id', 'group_id', 'created_by', 'paid_by', 'notes', 'split_type', 'created_at', 'updated_at', 'splits' => [['user_id', 'amount']]]]);
        $settlements = $this->getJson($this->path.'/settlements')->assertOk()->assertJsonPath('currency', 'MYR')->assertJsonCount(3, 'settlements')->assertJsonMissingPath('data')->json('settlements');
        $expected = [[$this->member->id, $this->owner->id, '20.00'], [$this->third->id, $this->owner->id, '30.00'], [$this->third->id, $this->member->id, '10.00']];
        usort($expected, fn ($a, $b) => strcmp($a[0], $b[0]) ?: strcmp($a[1], $b[1]));
        self::assertSame($expected, array_map(fn ($s) => [$s['from']['id'], $s['to']['id'], $s['amount']], $settlements));
        $summary = $this->getJson($this->path.'/summary')->assertOk()->assertJsonPath('data.total_expenses', '210.00')->json('data.members');
        foreach ($summary as $row) {
            $net = Money::cents($row['paid_total']) - Money::cents($row['share_total']);
            $negative = str_starts_with($row['net_balance'], '-');
            self::assertSame($net, Money::cents(ltrim($row['net_balance'], '-')) * ($negative ? -1 : 1));
        }
    }

    public function test_equal_remainders_and_nonparticipating_payer(): void
    {
        $expense = $this->create(['amount' => '100']);
        $ids = $this->payload()['participant_ids'];
        sort($ids, SORT_STRING);
        self::assertSame($ids, array_column($expense['splits'], 'user_id'));
        self::assertSame(['33.34', '33.33', '33.33'], array_column($expense['splits'], 'amount'));
        $expense = $this->create(['amount' => '10.01', 'participant_ids' => [$this->member->id, $this->third->id]]);
        self::assertCount(2, $expense['splits']);
        self::assertNotContains($this->owner->id, array_column($expense['splits'], 'user_id'));
    }

    public function test_atomic_edit_delete_recalculate_and_preserve_author(): void
    {
        $expense = $this->create(['paid_by' => $this->member->id]);
        $url = $this->path.'/expenses/'.$expense['id'];
        $custom = $this->payload(['amount' => '100.00', 'split_type' => 'custom', 'participant_ids' => [$this->member->id, $this->third->id], 'splits' => [['user_id' => $this->member->id, 'amount' => '0'], ['user_id' => $this->third->id, 'amount' => '100']]]);
        $this->patchJson($url, $custom)->assertOk()->assertJsonPath('data.created_by', $this->owner->id)->assertJsonCount(2, 'data.splits');
        $this->getJson($this->path.'/summary')->assertJsonPath('data.total_expenses', '100.00');
        $before = Expense::findOrFail($expense['id'])->load('splits')->toArray();
        $custom['amount'] = '99';
        $this->patchJson($url, $custom)->assertUnprocessable()->assertJsonValidationErrors('splits');
        self::assertSame($before, Expense::findOrFail($expense['id'])->load('splits')->toArray());
        $this->deleteJson($url)->assertNoContent();
        $this->assertSoftDeleted('expenses', ['id' => $expense['id']]);
        $this->assertDatabaseCount('expense_splits', 2);
        $this->getJson($url)->assertNotFound();
        $this->getJson($this->path.'/expenses')->assertJsonCount(0, 'data');
        $this->getJson($this->path.'/settlements')->assertJsonCount(0, 'settlements');
        $this->getJson($this->path.'/summary')->assertJsonPath('data.total_expenses', '0.00');
    }

    public function test_creator_only_permissions_payer_does_not_grant_access_and_scoping(): void
    {
        $expense = $this->create(['paid_by' => $this->third->id]);
        $url = $this->path.'/expenses/'.$expense['id'];
        $this->actingAs($this->third)->patchJson($url, $this->payload())->assertForbidden();
        $this->deleteJson($url)->assertForbidden();
        $this->actingAs($this->owner)->patchJson($url, $this->payload())->assertOk();
        $other = Group::factory()->create(['created_by' => $this->owner->id]);
        $wrong = '/api/groups/'.$other->id.'/expenses/'.$expense['id'];
        $this->getJson($wrong)->assertNotFound();
        $this->patchJson($wrong, $this->payload())->assertNotFound();
        $this->deleteJson($wrong)->assertNotFound();
        $this->actingAs(User::factory()->create());
        foreach (['expenses', 'summary', 'settlements'] as $route) {
            $this->getJson($this->path.'/'.$route)->assertForbidden();
        }
        $this->postJson($this->path.'/expenses', $this->payload())->assertForbidden();
    }

    public function test_former_members_debts_remain_but_new_expense_selection_is_rejected(): void
    {
        $expense = $this->create();
        $before = $this->getJson($this->path.'/settlements')->json();
        $this->deleteJson($this->path.'/members/'.$this->member->id)->assertNoContent();
        $this->getJson($this->path.'/settlements')->assertExactJson($before);
        $this->getJson($this->path.'/expenses/'.$expense['id'])->assertOk();
        $this->postJson($this->path.'/expenses', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('participant_ids');
        $this->postJson($this->path.'/expenses', $this->payload(['participant_ids' => [$this->owner->id], 'paid_by' => $this->member->id]))->assertUnprocessable()->assertJsonValidationErrors('paid_by');
        $this->actingAs($this->member)->getJson($this->path.'/settlements')->assertForbidden();
        $this->patchJson($this->path.'/expenses/'.$expense['id'], $this->payload())->assertForbidden();
    }

    public function test_sorting_and_stable_ties(): void
    {
        $first = $this->create(['description' => 'Zebra', 'amount' => '2.00', 'expense_date' => '2026-10-01']);
        $second = $this->create(['description' => 'Apple', 'amount' => '10.00']);
        $third = $this->create(['description' => 'Banana', 'amount' => '10.00']);
        $this->getJson($this->path.'/expenses?sort=amount&direction=asc')->assertOk()->assertJsonPath('data.0.id', $first['id']);
        $this->getJson($this->path.'/expenses?sort=description&direction=asc')->assertJsonPath('data.0.id', $second['id']);
        $this->getJson($this->path.'/expenses')->assertJsonPath('data.0.id', min($second['id'], $third['id']));
        $this->getJson($this->path.'/expenses?sort=invalid')->assertUnprocessable();
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_expense_payloads(array $replacement, string $field): void
    {
        $this->postJson($this->path.'/expenses', $this->payload($replacement))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('expense_splits', 0);
    }

    public static function invalidPayloads(): array
    {
        return [
            [['amount' => '-1'], 'amount'], [['amount' => '1.001'], 'amount'], [['amount' => '0'], 'amount'],
            [['amount' => 120.00], 'amount'], [['amount' => '10000000000.00'], 'amount'],
            [['participant_ids' => []], 'participant_ids'], [['split_type' => 'percentage'], 'split_type'],
            [['expense_date' => '2026-02-30'], 'expense_date'], [['created_by' => 'override'], 'created_by'],
            [['group_id' => 'override'], 'group_id'], [['split_type' => 'custom'], 'splits'],
            [['splits' => [['user_id' => 'irrelevant', 'amount' => '120.00']]], 'splits'],
        ];
    }

    public function test_custom_mismatch_duplicates_and_inactive_or_foreign_participants(): void
    {
        $custom = $this->payload(['split_type' => 'custom', 'splits' => [['user_id' => $this->owner->id, 'amount' => '120.00']]]);
        $this->postJson($this->path.'/expenses', $custom)->assertUnprocessable()->assertJsonValidationErrors('splits');
        $this->postJson($this->path.'/expenses', $this->payload(['participant_ids' => [$this->member->id, $this->member->id]]))->assertUnprocessable();
        $custom['participant_ids'] = [$this->member->id];
        $custom['splits'] = [['user_id' => $this->member->id, 'amount' => '60.00'], ['user_id' => $this->member->id, 'amount' => '60.00']];
        $this->postJson($this->path.'/expenses', $custom)->assertUnprocessable();
        $this->postJson($this->path.'/expenses', $this->payload(['participant_ids' => [User::factory()->create()->id]]))->assertUnprocessable();
    }

    public function test_delete_group_cascades_even_soft_deleted_expenses(): void
    {
        $expense = $this->create();
        $this->deleteJson($this->path.'/expenses/'.$expense['id'])->assertNoContent();
        $this->create();
        $this->deleteJson($this->path)->assertNoContent();
        foreach (['groups', 'group_members', 'expenses', 'expense_splits'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('users', 3);
    }
}
