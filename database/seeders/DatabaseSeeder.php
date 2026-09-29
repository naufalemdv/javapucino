<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            MaterialSeeder::class,
            DemoTransactionSeeder::class,
        ]);
        $this->call(ExpenseCategorySeeder::class);
    }
}
