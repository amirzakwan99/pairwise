<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\GroupService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $people = [];
        foreach (['Amir', 'Ali', 'Abu'] as $name) {
            $people[$name] = User::firstOrCreate(['email' => strtolower($name).'@example.test'], ['name' => $name, 'password' => 'password123']);
        }
        if (Group::where('created_by', $people['Amir']->id)->where('name', 'Langkawi Trip')->exists()) {
            return;
        }
        $groups = app(GroupService::class);
        $group = $groups->create($people['Amir'], ['name' => 'Langkawi Trip']);
        foreach (['Ali', 'Abu'] as $name) {
            $groups->add($people['Amir'], $group, $people[$name]->email);
        }
        $service = app(ExpenseService::class);
        foreach ([['Dinner', '120.00', 'Amir'], ['Grab', '60.00', 'Ali'], ['Drinks', '30.00', 'Abu'], ['Hotel', '300.00', 'Amir']] as [$description, $amount, $payer]) {
            $data = ['description' => $description, 'amount' => $amount, 'paid_by' => $people[$payer]->id, 'expense_date' => '2026-10-02', 'notes' => null, 'split_type' => 'equal', 'participant_ids' => array_map(fn ($user) => $user->id, array_values($people))];
            if ($description === 'Hotel') {
                $data['split_type'] = 'custom';
                $data['splits'] = [['user_id' => $people['Amir']->id, 'amount' => '150.00'], ['user_id' => $people['Ali']->id, 'amount' => '90.00'], ['user_id' => $people['Abu']->id, 'amount' => '60.00']];
            }
            $service->save($people[$payer], $group, $data);
        }
    }
}
