<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GroupService
{
    /** All group/member/expense mutations lock the group first to serialize membership changes. */
    public function locked(Group $group, callable $operation): mixed
    {
        return DB::transaction(function () use ($group, $operation) {
            $locked = Group::whereKey($group->id)->lockForUpdate()->firstOrFail();

            return $operation($locked);
        });
    }

    public function create(User $owner, array $data): Group
    {
        return DB::transaction(function () use ($owner, $data) {
            $group = Group::create(['name' => $data['name'], 'currency' => $data['currency'] ?? 'MYR', 'created_by' => $owner->id]);
            $group->memberships()->create(['user_id' => $owner->id, 'role' => 'owner']);

            return $group;
        });
    }

    public function rename(User $actor, Group $group, string $name): Group
    {
        return $this->locked($group, function (Group $group) use ($actor, $name) {
            Gate::forUser($actor)->authorize('update', $group);
            $group->update(['name' => $name]);

            return $group;
        });
    }

    public function add(User $actor, Group $group, string $email): GroupMember
    {
        return $this->locked($group, function (Group $group) use ($actor, $email) {
            Gate::forUser($actor)->authorize('update', $group);
            $user = User::where('email', $email)->first();
            if (! $user || $user->email !== $email) {
                throw ValidationException::withMessages(['email' => 'No registered account matches this exact email.']);
            }
            $existing = $group->memberships()->where('user_id', $user->id)->first();
            if ($existing && $existing->left_at === null) {
                throw ValidationException::withMessages(['email' => 'This person is already an active member.']);
            }
            if ($existing) {
                $existing->update(['left_at' => null, 'role' => 'member']);
            } else {
                $existing = $group->memberships()->create(['user_id' => $user->id, 'role' => 'member']);
            }

            return $existing->load('user');
        });
    }

    public function remove(User $actor, Group $group, string $userId, bool $leaving = false): void
    {
        $this->locked($group, function (Group $group) use ($actor, $userId, $leaving) {
            Gate::forUser($actor)->authorize($leaving ? 'view' : 'update', $group);
            $membership = $group->memberships()->where('user_id', $userId)->whereNull('left_at')->firstOrFail();
            if ($membership->role === 'owner') {
                throw ValidationException::withMessages(['member' => 'The owner cannot leave or be removed.']);
            }
            $membership->update(['left_at' => now()]);
        });
    }

    public function delete(User $actor, Group $group): void
    {
        $this->locked($group, function (Group $group) use ($actor) {
            Gate::forUser($actor)->authorize('delete', $group);
            $group->delete(); // FK cascades include soft-deleted expenses and their splits.
        });
    }
}
