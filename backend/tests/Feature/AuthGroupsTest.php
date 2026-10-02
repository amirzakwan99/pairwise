<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthGroupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost');
    }

    public function test_session_register_me_password_and_logout(): void
    {
        $this->getJson('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
        $payload = ['name' => 'Amir', 'email' => 'amir@example.test', 'password' => 'password123', 'password_confirmation' => 'password123'];
        $register = $this->postJson('/api/auth/register', $payload)->assertCreated()->assertJsonStructure(['data' => ['id', 'name', 'email']]);
        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $register->json('data.id'));
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.email', $payload['email'])->assertJsonMissingPath('data.password');
        $this->patchJson('/api/auth/password', ['current_password' => 'wrong', 'password' => 'changed123', 'password_confirmation' => 'changed123'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->patchJson('/api/auth/password', ['current_password' => 'password123', 'password' => 'changed123', 'password_confirmation' => 'changed123'])->assertOk();
        $this->postJson('/api/auth/logout')->assertNoContent();
        // Clear the guard cache to model the next independent HTTP request.
        app('auth')->forgetGuards();
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => $payload['email'], 'password' => 'password123'])->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => $payload['email'], 'password' => 'changed123'])->assertOk();
    }

    public function test_auth_validation_and_throttling(): void
    {
        $this->postJson('/api/auth/register', ['email' => 'bad'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'bad']);
        }
        $this->postJson('/api/auth/login', [])->assertStatus(429);
    }

    public function test_group_creation_owner_permissions_and_outsider_access(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $group = $this->actingAs($owner)->postJson('/api/groups', ['name' => 'Trip'])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('group_members', ['group_id' => $group, 'user_id' => $owner->id, 'role' => 'owner', 'left_at' => null]);
        $this->patchJson('/api/groups/'.$group, ['name' => 'Langkawi Trip'])->assertOk();
        $this->postJson('/api/groups/'.$group.'/members', ['name' => $member->name, 'email' => $member->email])->assertCreated()->assertJsonPath('data.active', true)->assertJsonPath('data.guest', true);
        $this->postJson('/api/groups/'.$group.'/members', ['name' => $member->name])->assertUnprocessable();
        $this->deleteJson('/api/groups/'.$group.'/members/'.$owner->id)->assertUnprocessable();
        $this->actingAs($member)->getJson('/api/groups/'.$group)->assertForbidden();
        $this->patchJson('/api/groups/'.$group, ['name' => 'No'])->assertForbidden();
        $this->deleteJson('/api/groups/'.$group)->assertForbidden();
        $this->postJson('/api/groups/'.$group.'/members', ['email' => $outsider->email])->assertForbidden();
        $this->actingAs($outsider)->getJson('/api/groups/'.$group)->assertForbidden();
        $this->getJson('/api/groups')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($owner)->getJson('/api/groups/missing')->assertNotFound();
    }

    public function test_creator_removes_and_readds_named_members_preserving_identity(): void
    {
        $group = Group::factory()->create();
        $owner = User::findOrFail($group->created_by);
        $path = '/api/groups/'.$group->id;
        $memberId = $this->actingAs($owner)->postJson($path.'/members', ['name' => 'Ali'])->assertCreated()->assertJsonPath('data.email', null)->json('data.id');
        $membershipId = $group->memberships()->where('user_id', $memberId)->value('id');
        $this->deleteJson($path.'/members/'.$memberId)->assertNoContent();
        $this->getJson($path.'/members')->assertOk()->assertJsonFragment(['id' => $memberId, 'active' => false]);
        $this->postJson($path.'/members', ['name' => 'Ali'])->assertCreated()->assertJsonPath('data.id', $memberId)->assertJsonPath('data.active', true)->assertJsonPath('data.role', 'member');
        self::assertSame($membershipId, $group->memberships()->where('user_id', $memberId)->value('id'));
        $this->deleteJson($path.'/members/'.$memberId)->assertNoContent();
    }

    public function test_group_creation_includes_initial_name_only_members(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $id = $this->actingAs($owner)->postJson('/api/groups', [
            'name' => 'Trip', 'member_names' => [' Ali ', 'Abu'],
        ])->assertCreated()->json('data.id');
        $this->getJson('/api/groups/'.$id.'/members')->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonFragment(['name' => 'Ali', 'email' => null, 'guest' => true, 'role' => 'member', 'active' => true])
            ->assertJsonFragment(['name' => 'Abu', 'email' => null, 'guest' => true, 'role' => 'member', 'active' => true]);
        self::assertSame(2, User::whereNull('password')->whereNull('email')->count());
        $this->patchJson('/api/groups/'.$id, ['name' => 'Renamed', 'member_names' => ['Bob']])
            ->assertUnprocessable()->assertJsonValidationErrors('member_names');
    }

    public function test_invalid_initial_members_never_leave_partial_groups_or_guests(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $this->actingAs($owner);
        foreach ([['Ali', 'ali'], ['Ali', '  '], ['Ali', str_repeat('x', 256)], ['Ali', 'OWNER']] as $names) {
            $this->postJson('/api/groups', ['name' => 'Trip', 'member_names' => $names])
                ->assertUnprocessable()->assertJsonValidationErrors('member_names.1');
            $this->assertDatabaseCount('groups', 0);
            $this->assertDatabaseCount('group_members', 0);
            $this->assertDatabaseCount('users', 1);
        }
        $this->postJson('/api/groups', ['name' => 'Trip', 'member_names' => 'Ali'])
            ->assertUnprocessable()->assertJsonValidationErrors('member_names');
    }

    public function test_nested_members_are_scoped_and_currency_is_myr_only(): void
    {
        $group = Group::factory()->create();
        $other = Group::factory()->create();
        $owner = User::findOrFail($group->created_by);
        $this->actingAs($owner)->deleteJson('/api/groups/'.$group->id.'/members/'.$other->created_by)->assertNotFound();
        $this->postJson('/api/groups', ['name' => 'Trip', 'currency' => 'USD'])->assertUnprocessable();
        $this->patchJson('/api/groups/'.$group->id, ['name' => 'Trip', 'currency' => 'MYR'])->assertUnprocessable();
    }

    public function test_name_only_and_optional_email_never_create_or_link_login_accounts(): void
    {
        $group = Group::factory()->create();
        $owner = User::findOrFail($group->created_by);
        $registered = User::factory()->create();
        $path = '/api/groups/'.$group->id;
        $this->actingAs($owner);
        $guest = $this->postJson($path.'/members', ['name' => 'Ali'])->assertCreated()->assertJsonPath('data.email', null)->assertJsonPath('data.guest', true)->json('data.id');
        $this->assertDatabaseHas('users', ['id' => $guest, 'email' => null, 'password' => null]);
        $contact = $this->postJson($path.'/members', ['name' => 'Abu', 'email' => $registered->email])->assertCreated()->assertJsonPath('data.email', $registered->email)->assertJsonPath('data.name', 'Abu')->assertJsonPath('data.guest', true)->json('data.id');
        self::assertNotSame($registered->id, $contact);
        $this->assertDatabaseHas('users', ['id' => $contact, 'email' => null, 'password' => null]);
        $this->assertDatabaseMissing('group_members', ['group_id' => $group->id, 'user_id' => $registered->id]);
        $this->postJson($path.'/members', ['name' => 'ALi'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson($path.'/members', ['email' => 'test@example.test'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson($path.'/members', ['name' => '   '])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson($path.'/members', ['name' => 'Bob', 'email' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson($path.'/members', ['name' => 'Bob', 'email' => 'unregistered@example.test'])->assertCreated();
        $other = Group::factory()->create(['created_by' => $owner->id]);
        $otherGuest = $this->postJson('/api/groups/'.$other->id.'/members', ['name' => 'Ali'])->assertCreated()->json('data.id');
        self::assertNotSame($guest, $otherGuest);
        $this->actingAs($registered)->getJson($path)->assertForbidden();
        $this->getJson('/api/groups')->assertJsonCount(0, 'data');
    }

    public function test_legacy_registered_members_and_expense_authors_cannot_access_creator_groups(): void
    {
        $group = Group::factory()->create();
        $legacyMember = User::factory()->create();
        $group->memberships()->create(['user_id' => $legacyMember->id, 'role' => 'member']);
        $expense = Expense::factory()->create(['group_id' => $group->id, 'created_by' => $legacyMember->id]);
        $path = '/api/groups/'.$group->id;
        $this->actingAs($legacyMember);
        foreach (['', '/members', '/expenses', '/summary', '/settlements', '/expenses/'.$expense->id] as $suffix) {
            $this->getJson($path.$suffix)->assertForbidden();
        }
        $this->getJson('/api/groups')->assertJsonCount(0, 'data');
        $this->postJson($path.'/members', ['name' => 'Bob'])->assertForbidden();
        $this->postJson($path.'/expenses', [])->assertForbidden();
        $this->patchJson($path.'/expenses/'.$expense->id, [])->assertForbidden();
        $this->deleteJson($path.'/expenses/'.$expense->id)->assertForbidden();
        $this->deleteJson($path.'/members/'.$legacyMember->id)->assertForbidden();
        $this->deleteJson($path)->assertForbidden();
        $this->actingAs(User::findOrFail($group->created_by))->getJson($path.'/expenses/'.$expense->id)->assertOk();
        $this->deleteJson($path.'/expenses/'.$expense->id)->assertNoContent();
    }

    public function test_guest_contact_email_cannot_be_used_to_login_or_reserve_registration(): void
    {
        $group = Group::factory()->create();
        $email = 'guest-contact@example.test';
        $this->actingAs(User::findOrFail($group->created_by))->postJson('/api/groups/'.$group->id.'/members', ['name' => 'Ali', 'email' => $email])->assertCreated();
        $this->postJson('/api/auth/logout')->assertNoContent();
        app('auth')->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'password123'])->assertUnprocessable();
        $this->postJson('/api/auth/register', ['name' => 'Ali account', 'email' => $email, 'password' => 'password123', 'password_confirmation' => 'password123'])->assertCreated();
        $this->getJson('/api/groups/'.$group->id)->assertForbidden();
    }

    public function test_seed_uses_one_owner_account_and_name_only_payers(): void
    {
        $this->seed();
        $group = Group::where('name', 'Langkawi Trip')->firstOrFail();
        self::assertSame(1, User::whereNotNull('password')->count());
        self::assertSame(2, User::whereNull('password')->count());
        self::assertSame([$group->created_by], $group->expenses()->pluck('created_by')->unique()->values()->all());
        self::assertSame(3, $group->expenses()->pluck('paid_by')->unique()->count());
        $this->actingAs(User::findOrFail($group->created_by))->getJson('/api/groups/'.$group->id.'/summary')->assertOk()->assertJsonPath('data.total_expenses', '510.00');
    }
}
