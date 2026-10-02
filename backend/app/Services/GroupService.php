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

            foreach ($data['member_names'] ?? [] as $index => $name) {
                try {
                    $this->add($owner, $group, ['name' => $name]);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['member_names.'.$index => $exception->errors()['name']]);
                }
            }

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

    public function add(User $actor, Group $group, array $data): GroupMember
    {
        return $this->locked($group, function (Group $group) use ($actor, $data) {
            Gate::forUser($actor)->authorize('update', $group);
            $email = $data['email'] ?? null;
            $existing = $group->memberships()->with('user')->get()->first(
                fn ($member) => mb_strtolower($member->user->name) === mb_strtolower($data['name'])
            );
            if ($existing && $existing->left_at === null) {
                throw ValidationException::withMessages(['name' => 'This name is already an active member of this group.']);
            }
            if ($existing) {
                $existing->update(['left_at' => null, 'role' => 'member', 'contact_email' => $existing->account_user_id ? $existing->contact_email : ($email ?? $existing->contact_email)]);
            } else {
                // A guest is a group-local identity for saved shares, not a login account.
                // Contact emails live on membership so they cannot reserve/claim a login.
                $user = User::create(['name' => $data['name'], 'email' => null, 'password' => null]);
                $existing = $group->memberships()->create(['user_id' => $user->id, 'role' => 'member', 'contact_email' => $email]);
            }

            return $existing->load('user');
        });
    }

    public function remove(User $actor, Group $group, string $userId): void
    {
        $this->locked($group, function (Group $group) use ($actor, $userId) {
            Gate::forUser($actor)->authorize('update', $group);
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
