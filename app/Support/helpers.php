<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /**
     * Ambil nilai pengaturan dari tabel settings (dengan cache).
     * setting() tanpa argumen mengembalikan seluruh pengaturan.
     */
    function setting(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return Setting::all_cached();
        }

        return Setting::get($key, $default);
    }
}

if (! function_exists('rupiah')) {
    /** Format Rupiah: 18000 => "Rp 18.000" */
    function rupiah(float|int|string|null $value, bool $prefix = true): string
    {
        $num = number_format((float) $value, 0, ',', '.');

        return $prefix ? 'Rp '.$num : $num;
    }
}

if (! function_exists('angka')) {
    /** Format angka tanpa prefix: 18000 => "18.000" */
    function angka(float|int|string|null $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }
}

if (! function_exists('queue_no')) {
    /** Nomor antrian 3 digit: 7 => "007" */
    function queue_no(int|string|null $no): string
    {
        return str_pad((string) ((int) $no), 3, '0', STR_PAD_LEFT);
    }
}

if (! function_exists('tax_of')) {
    /** BR-02: pajak = round(subtotal * tax_percent / 100) */
    function tax_of(float $subtotal, ?float $percent = null): float
    {
        $percent ??= (float) setting('tax_percent', 0);

        return round($subtotal * $percent / 100);
    }
}

if (! function_exists('board_slides')) {
    /** Daftar path gambar slide layar pelanggan (disk public), urut sesuai tampilan. */
    function board_slides(): array
    {
        $slides = json_decode((string) setting('board_slides', '[]'), true);

        return is_array($slides) ? array_values(array_filter($slides, 'is_string')) : [];
    }
}
