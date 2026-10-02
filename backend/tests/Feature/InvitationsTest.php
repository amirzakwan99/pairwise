<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\GroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost');
    }

    private function fixture(): array
    {
        $owner = User::factory()->create();
        $group = app(GroupService::class)->create($owner, ['name' => 'Trip', 'member_names' => ['Ali', 'Abu']]);
        $guest = $group->memberships()->where('role', 'member')->with('user')->get()->first(fn ($m) => $m->user->name === 'Ali');
        $token = $this->actingAs($owner)->postJson('/api/groups/'.$group->id.'/invitation')->assertCreated()->json('data.token');

        return [$owner, $group, $guest, $token];
    }

    public function test_join_updates_email_preserves_history_and_grants_expense_access(): void
    {
        [$owner, $group, $guest, $token] = $this->fixture();
        $payload = ['description' => 'Dinner', 'amount' => '10.01', 'paid_by' => $guest->user_id,
            'expense_date' => '2026-10-02', 'split_type' => 'equal', 'participant_ids' => [$owner->id, $guest->user_id]];
        $expense = app(ExpenseService::class)->save($owner, $group, $payload);
        $before = $this->getJson('/api/groups/'.$group->id.'/summary')->json('data');
        $account = User::factory()->create(['name' => 'Different account name']);
        $this->actingAs($account)->getJson('/api/groups/'.$group->id)->assertForbidden();
        $this->getJson('/api/invitations/'.$token)->assertOk()->assertJsonCount(2, 'data.members')->assertJsonMissingPath('data.members.0.email');
        $this->postJson('/api/invitations/'.$token.'/join', ['member_id' => $guest->user_id])->assertCreated()
            ->assertJsonPath('data.member.id', $guest->user_id)->assertJsonPath('data.member.email', $account->email)
            ->assertJsonPath('data.member.account_user_id', $account->id)->assertJsonPath('data.member.guest', false);
        $this->assertDatabaseHas('users', ['id' => $guest->user_id, 'email' => null, 'password' => null]);
        $this->assertDatabaseHas('expense_splits', ['expense_id' => $expense->id, 'user_id' => $guest->user_id]);
        $this->getJson('/api/groups')->assertOk()->assertJsonCount(1, 'data');
        foreach (['', '/members', '/expenses', '/settlements', '/expenses/'.$expense->id] as $suffix) {
            $this->getJson('/api/groups/'.$group->id.$suffix)->assertOk();
        }
        $after = $this->getJson('/api/groups/'.$group->id.'/summary')->assertOk()->json('data');
        $before['members'] = collect($before['members'])->sortBy('id')->values()->all();
        $after['members'] = collect($after['members'])->sortBy('id')->values()->all();
        self::assertSame($before, $after);
        $this->patchJson('/api/groups/'.$group->id.'/expenses/'.$expense->id, $payload + ['notes' => 'Updated by member'])->assertOk();
        $added = $this->postJson('/api/groups/'.$group->id.'/expenses', $payload)->assertCreated()->json('data.id');
        $this->assertDatabaseHas('expenses', ['id' => $added, 'created_by' => $account->id]);
        $this->deleteJson('/api/groups/'.$group->id.'/expenses/'.$added)->assertForbidden();
        $this->postJson('/api/groups/'.$group->id.'/members', ['name' => 'Bob'])->assertForbidden();
        $this->patchJson('/api/groups/'.$group->id, ['name' => 'No'])->assertForbidden();
        $this->deleteJson('/api/groups/'.$group->id)->assertForbidden();
        $this->postJson('/api/groups/'.$group->id.'/invitation')->assertForbidden();
        $this->deleteJson('/api/groups/'.$group->id.'/invitation')->assertForbidden();
    }

    public function test_name_can_be_claimed_once_and_account_cannot_claim_multiple_names(): void
    {
        [$owner, $group, $guest, $token] = $this->fixture();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $other = $group->memberships()->where('role', 'member')->where('user_id', '!=', $guest->user_id)->firstOrFail();
        $this->actingAs($first)->postJson('/api/invitations/'.$token.'/join', ['member_id' => $guest->user_id])->assertCreated();
        $this->postJson('/api/invitations/'.$token.'/join', ['member_id' => $other->user_id])->assertUnprocessable();
        $this->actingAs($second)->postJson('/api/invitations/'.$token.'/join', ['member_id' => $guest->user_id])->assertUnprocessable();
        $this->getJson('/api/invitations/'.$token)->assertJsonCount(1, 'data.members');
        $this->actingAs($owner)->postJson('/api/invitations/'.$token.'/join', ['member_id' => $other->user_id])->assertUnprocessable();
        $this->assertDatabaseHas('group_members', ['id' => $guest->id, 'account_user_id' => $first->id, 'contact_email' => $first->email]);
    }

    public function test_invitation_rotation_revocation_expiry_and_authentication(): void
    {
        [$owner, $group, $guest, $token] = $this->fixture();
        self::assertSame(hash('sha256', $token), $group->fresh()->invite_token_hash);
        $this->getJson('/api/groups/'.$group->id)->assertJsonMissingPath('data.invite_token_hash');
        $new = $this->postJson('/api/groups/'.$group->id.'/invitation')->assertCreated()->json('data.token');
        $this->getJson('/api/invitations/'.$token)->assertNotFound();
        $this->postJson('/api/invitations/'.$token.'/join', ['member_id' => $guest->user_id])->assertNotFound();
        $this->travel(8)->days();
        $this->getJson('/api/invitations/'.$new)->assertNotFound();
        $this->postJson('/api/invitations/'.$new.'/join', ['member_id' => $guest->user_id])->assertNotFound();
        $this->travelBack();
        $this->deleteJson('/api/groups/'.$group->id.'/invitation')->assertNoContent();
        $this->getJson('/api/invitations/'.$new)->assertNotFound();
        $this->getJson('/api/invitations/not-a-token')->assertNotFound();
        $this->postJson('/api/auth/logout')->assertNoContent();
        app('auth')->forgetGuards();
        $this->getJson('/api/invitations/'.$token)->assertUnauthorized();
        $this->postJson('/api/invitations/'.$token.'/join', ['member_id' => $guest->user_id])->assertUnauthorized();
    }

    public function test_foreign_removed_and_owner_names_cannot_be_claimed_and_removal_revokes_access(): void
    {
        [$owner, $group, $guest, $token] = $this->fixture();
        $account = User::factory()->create();
        $foreign = Group::factory()->create();
        $this->actingAs($account)->postJson('/api/invitations/'.$token.'/join', ['member_id' => $foreign->created_by])->assertNotFound();
        $this->postJson('/api/invitations/'.$token.'/join', ['member_id' => $owner->id])->assertNotFound();
        app(GroupService::class)->remove($owner, $group, $guest->user_id);
        $this->postJson('/api/invitations/'.$token.'/join', ['member_id' => $guest->user_id])->assertNotFound();
        app(GroupService::class)->add($owner, $group, ['name' => 'Ali']);
        $this->postJson('/api/invitations/'.$token.'/join', ['member_id' => $guest->user_id])->assertCreated();
        app(GroupService::class)->remove($owner, $group, $guest->user_id);
        $this->getJson('/api/groups/'.$group->id)->assertForbidden();
        $this->getJson('/api/groups')->assertJsonCount(0, 'data');
        $this->postJson('/api/groups/'.$group->id.'/expenses', [])->assertForbidden();
        app(GroupService::class)->add($owner, $group, ['name' => 'Ali', 'email' => 'wrong@example.test']);
        $this->getJson('/api/groups/'.$group->id)->assertOk();
        $this->assertDatabaseHas('group_members', ['id' => $guest->id, 'account_user_id' => $account->id, 'contact_email' => $account->email]);
    }
}
