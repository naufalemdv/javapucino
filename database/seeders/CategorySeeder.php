<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Kopi Susu',      'icon' => '🥤', 'sort_order' => 1],
            ['name' => 'Espresso Based', 'icon' => '☕', 'sort_order' => 2],
            ['name' => 'Non-Kopi',       'icon' => '🍵', 'sort_order' => 3],
            ['name' => 'Teman Ngopi',    'icon' => '🥐', 'sort_order' => 4],
        ];

        foreach ($rows as $r) {
            Category::updateOrCreate(['name' => $r['name']], [...$r, 'is_active' => true]);
        }
    }
}
