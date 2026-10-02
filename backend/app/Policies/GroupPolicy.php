<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function view(User $user, Group $group): bool
    {
        return $this->update($user, $group);
    }

    public function update(User $user, Group $group): bool
    {
        return $user->id === $group->created_by
            && $group->memberships()->where('user_id', $user->id)->whereNull('left_at')->where('role', 'owner')->exists();
    }

    public function delete(User $user, Group $group): bool
    {
        return $this->update($user, $group);
    }
}
