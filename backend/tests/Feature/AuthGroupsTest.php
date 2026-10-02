<?php

declare(strict_types=1);

namespace Tests\Feature;

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
        $this->postJson('/api/groups/'.$group.'/members', ['email' => $member->email])->assertCreated()->assertJsonPath('data.active', true);
        $this->postJson('/api/groups/'.$group.'/members', ['email' => $member->email])->assertUnprocessable();
        $this->deleteJson('/api/groups/'.$group.'/members/me')->assertUnprocessable();
        $this->deleteJson('/api/groups/'.$group.'/members/'.$owner->id)->assertUnprocessable();
        $this->actingAs($member)->getJson('/api/groups/'.$group)->assertOk();
        $this->patchJson('/api/groups/'.$group, ['name' => 'No'])->assertForbidden();
        $this->deleteJson('/api/groups/'.$group)->assertForbidden();
        $this->postJson('/api/groups/'.$group.'/members', ['email' => $outsider->email])->assertForbidden();
        $this->actingAs($outsider)->getJson('/api/groups/'.$group)->assertForbidden();
        $this->getJson('/api/groups')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($owner)->getJson('/api/groups/missing')->assertNotFound();
    }

    public function test_leave_and_rejoin_preserve_membership_and_revoke_access(): void
    {
        $group = Group::factory()->create();
        $owner = User::findOrFail($group->created_by);
        $member = User::factory()->create();
        $path = '/api/groups/'.$group->id;
        $this->actingAs($owner)->postJson($path.'/members', ['email' => $member->email])->assertCreated();
        $membershipId = $group->memberships()->where('user_id', $member->id)->value('id');
        $this->actingAs($member)->deleteJson($path.'/members/me')->assertNoContent();
        $this->getJson($path)->assertForbidden();
        $this->getJson('/api/groups')->assertJsonCount(0, 'data');
        $this->actingAs($owner)->getJson($path.'/members')->assertOk()->assertJsonFragment(['id' => $member->id, 'active' => false]);
        $this->postJson($path.'/members', ['email' => $member->email])->assertCreated()->assertJsonPath('data.role', 'member');
        self::assertSame($membershipId, $group->memberships()->where('user_id', $member->id)->value('id'));
        $this->deleteJson($path.'/members/'.$member->id)->assertNoContent();
        $this->actingAs($member)->getJson($path)->assertForbidden();
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
}
