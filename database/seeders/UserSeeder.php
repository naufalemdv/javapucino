<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Bagas Prakoso',  'username' => 'admin', 'role' => 'admin', 'is_active' => true],
            ['name' => 'Rina Oktaviani', 'username' => 'rina',  'role' => 'kasir', 'is_active' => true],
            ['name' => 'Dimas Aryo',     'username' => 'dimas', 'role' => 'kasir', 'is_active' => true],
            ['name' => 'Sella Maharani', 'username' => 'sella', 'role' => 'kasir', 'is_active' => false],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(
                ['username' => $u['username']],
                [...$u, 'password' => 'password123', 'last_login_at' => now()->subDay()],
            );
        }
    }
}
