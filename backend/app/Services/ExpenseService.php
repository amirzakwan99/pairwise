<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(private GroupService $groups) {}

    public function save(User $actor, Group $group, array $data, ?string $id = null): Expense
    {
        return $this->groups->locked($group, function (Group $group) use ($actor, $data, $id) {
            Gate::forUser($actor)->authorize('view', $group);
            $expense = $id ? $group->expenses()->findOrFail($id) : null;
            if ($expense) {
                Gate::forUser($actor)->authorize('update', $expense);
            }
            $activeIds = $group->memberships()->whereNull('left_at')->pluck('user_id')->all();
            if (! in_array($data['paid_by'], $activeIds, true)) {
                throw ValidationException::withMessages(['paid_by' => 'Choose an active member as payer.']);
            }
            if (array_diff($data['participant_ids'], $activeIds)) {
                throw ValidationException::withMessages(['participant_ids' => 'All participants must be active group members.']);
            }
            $shares = $data['split_type'] === 'equal'
                ? Money::equal(Money::cents($data['amount']), $data['participant_ids'])
                : array_column(array_map(fn ($split) => ['user_id' => $split['user_id'], 'amount' => Money::cents($split['amount'])], $data['splits']), 'amount', 'user_id');
            $attributes = array_intersect_key($data, array_flip(['description', 'paid_by', 'split_type', 'expense_date']));
            $attributes['notes'] = $data['notes'] ?? null;
            $attributes['amount'] = Money::decimal(Money::cents($data['amount']));
            if ($expense) {
                $expense->update($attributes);
                $expense->splits()->delete();
            } else {
                $expense = $group->expenses()->create($attributes + ['created_by' => $actor->id]);
            }
            foreach ($shares as $userId => $cents) {
                $expense->splits()->create(['user_id' => $userId, 'amount' => Money::decimal($cents)]);
            }

            return $expense->load('splits');
        });
    }

    public function delete(User $actor, Group $group, string $id): void
    {
        $this->groups->locked($group, function (Group $group) use ($actor, $id) {
            Gate::forUser($actor)->authorize('view', $group);
            $expense = $group->expenses()->findOrFail($id);
            Gate::forUser($actor)->authorize('delete', $expense);
            $expense->delete();
        });
    }
}
