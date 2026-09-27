<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi khusus Javapucino POS
|--------------------------------------------------------------------------
| Nilai di sini adalah default aplikasi. Nilai operasional (nama toko,
| pajak, NMID QRIS, dsb.) disimpan di tabel `settings` dan diakses lewat
| helper setting('key').
*/

return [
    'invoice_prefix' => 'TRX',

    'shift_types' => [
        'pagi' => ['label' => 'Pagi', 'range' => '07.00 - 15.00'],
        'sore' => ['label' => 'Sore', 'range' => '15.00 - 22.00'],
    ],

    'payment_methods' => [
        'cash' => 'Tunai',
        'qris' => 'QRIS',
    ],

    'units' => ['gram', 'ml', 'liter', 'kg', 'pcs'],

    'icons' => [
        'product'  => ['☕', '🥤', '🥛', '🍵', '🧋', '🍫', '🍮', '🌿', '🍓', '🍋', '🥐', '🍩', '🍞', '🌰', '⚫', '🍌'],
        'category' => ['☕', '🥤', '🍵', '🥐', '🧋', '🍫', '🍰', '🥗'],
    ],

    'default_settings' => [
        'store_name'          => 'Javapucino',
        'store_address'       => 'Booth Lt. 1, Mall Kelapa Gading, Jakarta Utara',
        'store_phone'         => '0812-1010-2024',
        'tax_percent'         => '0',
        'paper_width'         => '58mm',
        'qris_nmid'           => 'ID1026384756201',
        'receipt_footer'      => 'Semua Bisa Ngopi! Terima kasih 🙏',
        'low_stock_threshold' => '5',
        'board_slides'        => '[]',   // JSON path gambar layar pelanggan (disk public)
        'board_slide_seconds' => '6',
    ],
];
