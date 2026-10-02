<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return ['group_id' => Group::factory(), 'description' => fake()->words(2, true), 'amount' => '10.01', 'split_type' => 'equal', 'expense_date' => '2026-10-02', 'notes' => null];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Expense $expense) {
            $owner = Group::findOrFail($expense->group_id)->created_by;
            $expense->paid_by ??= $owner;
            $expense->created_by ??= $owner;
        })->afterCreating(fn (Expense $expense) => $expense->splits()->create(['user_id' => $expense->paid_by, 'amount' => $expense->amount]));
    }
}
