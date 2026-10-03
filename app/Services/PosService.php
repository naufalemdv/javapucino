<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Shift;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Inti proses kasir: simpan transaksi (FR-12) dan void (FR-23).
 *
 * Seluruh proses dibungkus DB transaction dengan lockForUpdate() pada
 * stok produk untuk mencegah race condition (NFR Integritas Data).
 */
class PosService
{
    /**
     * @param  array<int, array{product_id:int, qty:int}>  $items
     */
    public function checkout(
        array $items,
        string $paymentMethod,
        float $paidAmount,
        ?string $customerName,
        User $cashier,
        Shift $shift,
    ): Transaction {
        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'Keranjang masih kosong.']);
        }

        return DB::transaction(function () use ($items, $paymentMethod, $paidAmount, $customerName, $cashier, $shift) {
            $ids = collect($items)->pluck('product_id')->unique()->all();

            /** @var \Illuminate\Support\Collection<int, Product> $products */
            $products = Product::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            $lines    = [];
            $subtotal = 0.0;

            foreach ($items as $row) {
                $qty     = max(1, (int) ($row['qty'] ?? 0));
                $product = $products->get((int) $row['product_id']);

                if (! $product || ! $product->is_active) {
                    throw ValidationException::withMessages([
                        'items' => 'Ada menu yang sudah tidak aktif. Muat ulang halaman kasir.',
                    ]);
                }

                // BR-05: stok tidak boleh negatif
                if ($product->stock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => "Stok {$product->name} tinggal {$product->stock}.",
                    ]);
                }

                // BR-04: harga & HPP diambil dari master lalu di-snapshot
                $lineSubtotal = (float) $product->price * $qty;
                $subtotal    += $lineSubtotal;

                $lines[] = [
                    'product'      => $product,
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'icon'         => $product->icon,
                    'price'        => (float) $product->price,
                    'hpp'          => (float) $product->hpp,
                    'qty'          => $qty,
                    'subtotal'     => $lineSubtotal,
                ];
            }

            // BR-02: pajak & total
            $taxPercent = (float) setting('tax_percent', 0);
            $taxAmount  = tax_of($subtotal, $taxPercent);
            $total      = $subtotal + $taxAmount;

            // BR-03: validasi pembayaran
            if ($paymentMethod === 'cash') {
                if ($paidAmount < $total) {
                    throw ValidationException::withMessages([
                        'paid_amount' => 'Uang yang diterima kurang dari total tagihan.',
                    ]);
                }
                $change = $paidAmount - $total;
            } else {
                $paidAmount = $total;
                $change     = 0.0;
            }
            $queueBusy = Transaction::today()->completed()
            ->whereIn('queue_status', ['called', 'waiting'])
            ->exists();
            $transaction = Transaction::create([
                'invoice_no'     => Transaction::nextInvoiceNo(),
                'shift_id'       => $shift->id,
                'user_id'        => $cashier->id,
                'customer_name'  => $customerName ?: null,
                'queue_no'       => Transaction::nextQueueNo(),
                'queue_status'   => $queueBusy ? 'waiting' : 'called',
                'subtotal'       => $subtotal,
                'tax_percent'    => $taxPercent,
                'tax_amount'     => $taxAmount,
                'total'          => $total,
                'payment_method' => $paymentMethod,
                'paid_amount'    => $paidAmount,
                'change_amount'  => $change,
                'qris_reference' => $paymentMethod === 'qris' ? 'QR'.substr((string) now()->getTimestampMs(), -9) : null,
                'status'         => 'completed',
                'printed_count'  => 0,
            ]);

            foreach ($lines as $line) {
                $product = $line['product'];
                unset($line['product']);

                $transaction->items()->create($line);

                StockService::moveProduct(
                    $product,
                    -$line['qty'],
                    'sale',
                    $transaction,
                    'Penjualan '.$transaction->invoice_no,
                );
            }

            AuditLogger::log(
                'create',
                sprintf(
                    'Transaksi %s · antrian %s · %s · %s',
                    $transaction->invoice_no,
                    $transaction->queueLabel(),
                    rupiah($total),
                    $transaction->paymentLabel(),
                ),
                $transaction,
                null,
                $transaction->only(['invoice_no', 'total', 'payment_method']),
            );

            return $transaction->load('items');
        });
    }

    /**
     * FR-23 / BR-06: void transaksi, stok dikembalikan, data tetap tersimpan.
     */
    public function void(Transaction $transaction, string $reason, User $admin): Transaction
    {
        return DB::transaction(function () use ($transaction, $reason, $admin) {
            $fresh = Transaction::where('id', $transaction->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status === 'void') {
                throw ValidationException::withMessages([
                    'void_reason' => 'Transaksi ini sudah pernah di-void.',
                ]);
            }

            $old = $fresh->only(['status', 'voided_by', 'voided_at', 'void_reason']);

            foreach ($fresh->items()->with('product')->get() as $item) {
                if ($item->product) {
                    StockService::moveProduct(
                        $item->product,
                        $item->qty,
                        'void_return',
                        $fresh,
                        'Void '.$fresh->invoice_no,
                    );
                }
            }

            $fresh->update([
                'status'       => 'void',
                'voided_by'    => $admin->id,
                'voided_at'    => now(),
                'void_reason'  => $reason,
                'queue_status' => 'done',
            ]);

            AuditLogger::log(
                'void',
                sprintf('Void transaksi %s · %s · alasan: %s', $fresh->invoice_no, rupiah($fresh->total), $reason),
                $fresh,
                $old,
                $fresh->only(['status', 'voided_by', 'voided_at', 'void_reason']),
            );

            return $fresh;
        });
    }
}
