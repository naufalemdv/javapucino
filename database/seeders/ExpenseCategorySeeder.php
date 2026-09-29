<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Listrik',         'type' => 'operational', 'is_locked' => false, 'sort_order' => 10],
            ['name' => 'WiFi / Internet', 'type' => 'operational', 'is_locked' => false, 'sort_order' => 20],
            ['name' => 'Gaji Karyawan',   'type' => 'operational', 'is_locked' => false, 'sort_order' => 30],
            ['name' => 'Sewa Tempat',     'type' => 'operational', 'is_locked' => false, 'sort_order' => 40],
            ['name' => 'Perlengkapan',    'type' => 'operational', 'is_locked' => false, 'sort_order' => 50],
            ['name' => 'Lain-lain',       'type' => 'operational', 'is_locked' => false, 'sort_order' => 80],
            // Terkunci: dipakai otomatis oleh restock bahan
            ['name' => 'Pembelian Bahan', 'type' => 'inventory',   'is_locked' => true,  'sort_order' => 90],
        ];

        foreach ($rows as $row) {
            ExpenseCategory::firstOrCreate(
                ['name' => $row['name']],
                $row + ['is_active' => true],
            );
        }
    }
}