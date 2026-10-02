<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseSplitFactory extends Factory
{
    public function definition(): array
    {
        $expense = Expense::factory()->create();
        $participant = User::factory()->create();
        $expense->group->memberships()->create(['user_id' => $participant->id, 'role' => 'member']);
        $expense->splits()->delete();

        return ['expense_id' => $expense->id, 'user_id' => $participant->id, 'amount' => $expense->amount];
    }
}
