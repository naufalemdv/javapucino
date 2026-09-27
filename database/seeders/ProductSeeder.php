<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $cat = Category::pluck('id', 'name');

        // Daftar menu resmi Javapucino. HPP & stok awal diisi admin lewat CMS.
        $rows = [
            ['Espresso Based', 'ICA-1', 'Ice Crisp Americano (1 Shoot)', '☕',  5000, 0, 50, false],
            ['Espresso Based', 'ICA-2', 'Ice Crisp Americano (2 Shoot)', '☕',  7000, 0, 50, false],
            ['Espresso Based', 'ICA-3', 'Ice Crisp Americano (3 Shoot)', '☕',  9000, 0, 50, false],
            ['Espresso Based', 'SPA-1', 'Sparkling Americano',           '☕',  8000, 0, 50, false],
            ['Kopi Susu',      'CFL-N', 'Cafe Latte (Normal)',           '🥤',  8000, 0, 50, false],
            ['Kopi Susu',      'CFL-S', 'Cafe Latte (Strong)',           '🥤', 10000, 0, 50, false],
            ['Kopi Susu',      'CNL-N', 'Cinnamon Latte (Normal)',       '🥛',  8000, 0, 50, false],
            ['Kopi Susu',      'CNL-S', 'Cinnamon Latte (Strong)',       '🥛', 10000, 0, 50, false],
            ['Non-Kopi',       'NC-01', 'Lemon Tea',                     '🍋',  5000, 0, 50, false],
            ['Non-Kopi',       'NC-02', 'Lemonade',                      '🍋',  5000, 0, 50, false],
            ['Non-Kopi',       'NC-03', 'Peach Tea',                     '🍑',  5000, 0, 50, false],
            ['Non-Kopi',       'NC-04', 'Dark Chocolate',                '🍫', 10000, 0, 50, false],
            ['Non-Kopi',       'NC-05', 'Es Teh Java',                   '🧋',  5000, 0, 50, false],
            ['Non-Kopi',       'SPL-1', 'Sparkling Lemonade',            '🍋',  8000, 0, 50, false],
            ['Non-Kopi',       'SPT-1', 'Shocking Tea',                  '🧋',  7000, 0, 50, false],
        ];

        foreach ($rows as [$catName, $sku, $name, $icon, $price, $hpp, $stock, $best]) {
            $product = Product::withTrashed()->updateOrCreate(['sku' => $sku], [
                'category_id'    => $cat[$catName],
                'name'           => $name,
                'icon'           => $icon,
                'price'          => $price,
                'hpp'            => $hpp,
                'stock'          => $stock,
                'is_best_seller' => $best,
                'is_active'      => true,
            ]);

            if ($product->trashed()) {
                $product->restore();
            }
        }

        // Menu di luar daftar resmi disembunyikan (soft delete, riwayat transaksi tetap utuh)
        Product::whereNotIn('sku', array_column($rows, 1))
            ->orWhereNull('sku')
            ->get()
            ->each->delete();
    }
}
