<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PosService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function __construct(private readonly PosService $pos)
    {
    }

    /** FR-22 */
    public function index(Request $request)
    {
        $transactions = $this->query($request)->withCount('items')->paginate(30)->withQueryString();

        return view('admin.transactions.index', [
            'transactions' => $transactions,
            'cashiers'     => User::where('role', 'kasir')->orderBy('name')->get(),
            'filters'      => $request->only(['q', 'filter', 'kasir', 'dari', 'sampai']),
            'total'        => Transaction::count(),
        ]);
    }

    public function show(Transaction $transaction)
    {
        return redirect()->route('struk.show', $transaction);
    }

    /** FR-23 */
    public function void(Request $request, Transaction $transaction)
    {
        $data = $request->validate([
            'void_reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['void_reason' => 'alasan void']);

        $this->pos->void($transaction, $data['void_reason'], $request->user());

        return back()->with('success', 'Transaksi '.$transaction->invoice_no.' di-void. Stok telah dikembalikan.');
    }

    /** Export CSV (dibuka di Excel). */
    public function export(Request $request): StreamedResponse
    {
        $rows = $this->query($request)->with(['cashier', 'items'])->get();

        $filename = 'transaksi-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM agar Excel membaca UTF-8

            fputcsv($out, ['No. Transaksi', 'Tanggal', 'Kasir', 'Pelanggan', 'Metode', 'Subtotal', 'Pajak', 'Total', 'Status', 'Item']);

            foreach ($rows as $t) {
                fputcsv($out, [
                    $t->invoice_no,
                    $t->created_at->format('d/m/Y H:i'),
                    $t->cashier?->name,
                    $t->customer_name,
                    $t->paymentLabel(),
                    $t->subtotal,
                    $t->tax_amount,
                    $t->total,
                    $t->status,
                    $t->items->map(fn ($i) => $i->product_name.' x'.$i->qty)->implode('; '),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function query(Request $request)
    {
        $filter = $request->string('filter')->toString() ?: 'all';

        return Transaction::with('cashier')
            ->search($request->string('q')->toString())
            ->when($filter === 'void', fn ($q) => $q->where('status', 'void'))
            ->when(in_array($filter, ['cash', 'qris'], true), fn ($q) => $q->completed()->where('payment_method', $filter))
            ->when($request->integer('kasir'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->date('dari'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date('sampai'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest();
    }
}
