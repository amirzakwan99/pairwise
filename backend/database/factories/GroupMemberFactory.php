<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupMemberFactory extends Factory
{
    public function definition(): array
    {
        return ['group_id' => Group::factory(), 'user_id' => User::factory()->guest(), 'role' => 'member', 'left_at' => null, 'contact_email' => null];
    }
}
