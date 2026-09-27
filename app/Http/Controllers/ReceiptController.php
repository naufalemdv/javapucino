<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    /** FR-13: tampilkan struk. */
    public function show(Request $request, Transaction $transaction)
    {
        $this->authorizeView($request, $transaction);

        $transaction->load(['items', 'cashier', 'shift']);

        return view('receipt.show', [
            'trx'   => $transaction,
            'fresh' => (bool) session('fresh'),
        ]);
    }

    /** FR-14: halaman cetak (auto print) + menambah printed_count. */
    public function print(Request $request, Transaction $transaction)
    {
        $this->authorizeView($request, $transaction);

        $transaction->increment('printed_count');
        $transaction->refresh()->load(['items', 'cashier']);

        AuditLogger::log('print', 'Cetak struk '.$transaction->invoice_no.' (cetakan ke-'.$transaction->printed_count.')', $transaction);

        return view('receipt.print', ['trx' => $transaction]);
    }

    private function authorizeView(Request $request, Transaction $transaction): void
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || $transaction->user_id === $user->id,
            403,
            'Anda hanya dapat melihat struk transaksi Anda sendiri.'
        );
    }
}
