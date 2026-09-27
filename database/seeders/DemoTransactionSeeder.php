<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data demo 7 hari terakhir supaya dashboard, laporan, dan antrian
 * langsung terisi setelah `php artisan migrate --seed`.
 *
 * Jalankan hanya di lingkungan pengembangan.
 */
class DemoTransactionSeeder extends Seeder
{
    public function run(): void
    {
        if (Transaction::exists()) {
            $this->command?->warn('Transaksi sudah ada, seeder demo dilewati.');

            return;
        }

        $admin    = User::where('username', 'admin')->first();
        $kasir    = User::where('username', 'rina')->first();
        $kasir2   = User::where('username', 'dimas')->first();
        $products = Product::all();
        $tax      = (float) setting('tax_percent', 0);

        for ($d = 6; $d >= 0; $d--) {
            $day   = now()->subDays($d)->startOfDay();
            $user  = $d % 2 ? $kasir2 : $kasir;
            $isOpen = ($d === 0);

            $shift = Shift::create([
                'user_id'      => $user->id,
                'shift_type'   => $d % 2 ? 'sore' : 'pagi',
                'opened_at'    => $day->copy()->setTime(7, 30),
                'closed_at'    => $isOpen ? null : $day->copy()->setTime(15, 30),
                'opening_cash' => 300000,
                'status'       => $isOpen ? 'open' : 'closed',
            ]);

            $count = $isOpen ? 12 : random_int(14, 22);
            $seq   = 0;

            for ($i = 0; $i < $count; $i++) {
                $hour = random_int(8, $isOpen ? max(9, (int) now()->format('H')) : 19);
                $when = $day->copy()->setTime($hour, random_int(0, 59));

                if ($when->isFuture()) {
                    $when = now()->subMinutes(random_int(5, 120));
                }

                $picked = $products->shuffle()->take(random_int(1, 3));
                $items  = [];
                $sub    = 0.0;

                foreach ($picked as $p) {
                    $qty  = random_int(1, 2);
                    $line = (float) $p->price * $qty;
                    $sub += $line;

                    $items[] = [
                        'product_id'   => $p->id,
                        'product_name' => $p->name,
                        'icon'         => $p->icon,
                        'price'        => $p->price,
                        'hpp'          => $p->hpp,
                        'qty'          => $qty,
                        'subtotal'     => $line,
                    ];
                }

                $taxAmount = round($sub * $tax / 100);
                $total     = $sub + $taxAmount;
                $isCash    = random_int(0, 100) > 42;
                $paid      = $isCash ? ceil($total / 5000) * 5000 : $total;
                $seq++;

                $trx = Transaction::create([
                    'invoice_no'     => 'TRX-'.$day->format('Ymd').'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                    'shift_id'       => $shift->id,
                    'user_id'        => $user->id,
                    'customer_name'  => collect([null, 'Budi', 'Sari', 'Rizky', 'Antrian 12', null])->random(),
                    'queue_no'       => $seq,
                    'queue_status'   => $isOpen ? ($i >= $count - 3 ? 'waiting' : ($i === $count - 4 ? 'called' : 'done')) : 'done',
                    'subtotal'       => $sub,
                    'tax_percent'    => $tax,
                    'tax_amount'     => $taxAmount,
                    'total'          => $total,
                    'payment_method' => $isCash ? 'cash' : 'qris',
                    'paid_amount'    => $paid,
                    'change_amount'  => $isCash ? $paid - $total : 0,
                    'qris_reference' => $isCash ? null : 'QR'.random_int(100000000, 999999999),
                    'status'         => 'completed',
                    'printed_count'  => 1,
                    'created_at'     => $when,
                    'updated_at'     => $when,
                ]);

                foreach ($items as $item) {
                    $trx->items()->create($item);
                }
            }

            if (! $isOpen) {
                $cash     = (float) $shift->completedTransactions()->where('payment_method', 'cash')->sum('total');
                $expected = 300000 + $cash;
                $actual   = $expected + collect([0, 0, 0, -2000, 5000])->random();

                $shift->update([
                    'expected_cash' => $expected,
                    'actual_cash'   => $actual,
                    'difference'    => $actual - $expected,
                    'note'          => 'Rekonsiliasi selesai.',
                ]);
            }
        }

        // satu transaksi lama di-void sebagai contoh
        $void = Transaction::whereDate('created_at', '<', now()->toDateString())->inRandomOrder()->first();

        if ($void) {
            $void->update([
                'status'      => 'void',
                'voided_by'   => $admin->id,
                'voided_at'   => $void->created_at->copy()->addMinutes(15),
                'void_reason' => 'Pelanggan batal, pesanan salah input.',
            ]);
        }

        // contoh audit log
        $samples = [
            ['update',  'Pengaturan pajak diperbarui'],
            ['create',  'Produk "Caramel Latte" ditambahkan'],
            ['restock', 'Restock Cup Plastik 16oz +200 pcs'],
            ['login',   'Login berhasil sebagai Administrator'],
        ];

        foreach ($samples as $i => [$action, $desc]) {
            AuditLog::create([
                'user_id'     => $admin->id,
                'action'      => $action,
                'description' => $desc,
                'ip_address'  => '103.148.12.'.(20 + $i),
                'user_agent'  => 'Chrome/Windows',
                'created_at'  => now()->subHours($i + 2),
                'updated_at'  => now()->subHours($i + 2),
            ]);
        }

        $this->command?->info('Data demo 7 hari berhasil dibuat.');
    }
}
