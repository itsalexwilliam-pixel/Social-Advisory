<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;

class TempAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $account = Account::firstOrCreate(
            ['name' => 'Social Security Administration'],
            ['plan_id' => null]
        );

        $user = User::updateOrCreate(
            ['email' => 'admin@ssa.local'],
            [
                'name' => 'Admin',
                'role' => 'admin',
                'account_id' => $account->id,
                'password' => 'Admin@12345',
            ]
        );

        if ((int) $account->owner_user_id !== (int) $user->id) {
            $account->owner_user_id = $user->id;
            $account->save();
        }

        $account->users()->syncWithoutDetaching([
            $user->id => ['role' => 'owner'],
        ]);
    }
}
