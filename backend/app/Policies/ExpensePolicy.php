<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function update(User $user, Expense $expense): bool
    {
        $policy = new GroupPolicy;

        return $policy->view($user, $expense->group) && ($expense->created_by === $user->id || $policy->update($user, $expense->group));
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->update($user, $expense);
    }
}
