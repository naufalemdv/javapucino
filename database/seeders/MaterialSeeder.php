<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['Biji Kopi Arabika Gayo', 'gram', 4250,  2000],
            ['Susu UHT Full Cream',    'ml',   12400, 5000],
            ['Gula Aren Cair',         'ml',   1650,  2000],
            ['Cup Plastik 16oz',       'pcs',  248,   100],
            ['Sedotan Boba',           'pcs',  84,    150],
            ['Es Batu Kristal',        'kg',   26,    10],
            ['Bubuk Matcha',           'gram', 820,   300],
        ];

        foreach ($rows as [$name, $unit, $stock, $min]) {
            Material::updateOrCreate(['name' => $name], [
                'unit' => $unit, 'stock' => $stock, 'min_stock' => $min,
            ]);
        }
    }
}
