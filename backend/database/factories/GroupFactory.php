<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return ['name' => fake()->words(3, true), 'currency' => 'MYR', 'created_by' => User::factory()];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Group $group) => $group->memberships()->create(['user_id' => $group->created_by, 'role' => 'owner']));
    }
}
