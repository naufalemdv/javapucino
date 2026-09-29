<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetTransactionData extends Command
{
    protected $signature = 'javapucino:reset-transaksi {--stok : Nolkan juga stok produk & bahan}';

    protected $description = 'Kosongkan data transaksi, shift, pergerakan stok, pengeluaran, dan audit log. Data master tidak disentuh.';

    private const TABLES = [
        'transaction_items',
        'transactions',
        'shifts',
        'expenses',
        'stock_movements',
        'audit_logs',
    ];

    public function handle(): int
    {
        $this->warn('Perintah ini mengosongkan seluruh data transaksi dan tidak bisa dibatalkan.');
        $this->line('Data master (produk, kategori, bahan, pengguna, pengaturan) tetap aman.');

        if (! $this->confirm('Sudah backup database dan yakin mau lanjut?', false)) {
            $this->info('Dibatalkan.');

            return self::FAILURE;
        }

        Schema::disableForeignKeyConstraints();

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                $this->line("  dilewati (tabel tidak ada): {$table}");
                continue;
            }

            $count = DB::table($table)->count();
            DB::table($table)->truncate();
            $this->line("  dikosongkan: {$table} ({$count} baris)");
        }

        Schema::enableForeignKeyConstraints();

        if ($this->option('stok')) {
            DB::table('products')->update(['stock' => 0]);
            DB::table('materials')->update(['stock' => 0]);
            $this->line('  stok produk & bahan dinolkan');
        }

        $this->newLine();
        $this->info('Selesai. Sistem siap dipakai dengan data asli.');

        return self::SUCCESS;
    }
}