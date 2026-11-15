<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LocalUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        foreach ([
            ['name' => 'Administrator', 'email' => 'admin@example.test', 'role' => 'admin'],
            ['name' => 'Production User', 'email' => 'production@example.test', 'role' => 'production'],
            ['name' => 'QC/QA User', 'email' => 'qc@example.test', 'role' => 'qc_qa'],
            ['name' => 'Warehouse User', 'email' => 'warehouse@example.test', 'role' => 'warehouse'],
            ['name' => 'PPIC User', 'email' => 'ppic@example.test', 'role' => 'ppic'],
        ] as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'password' => Hash::make('MudalayaLocal2026!'),
                ]
            );
        }
    }
}
