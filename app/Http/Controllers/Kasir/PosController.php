<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\PosService;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function __construct(private readonly PosService $pos) {}

    /** FR-05..08 */
    public function index(Request $request)
    {
        $categories = Category::active()->ordered()->get();

        $products = Product::active()
            ->with('category')
            ->when($request->integer('kategori'), fn ($q, $id) => $q->where('category_id', $id))
            ->search($request->string('q')->toString())
            ->orderBy('name')
            ->get();

        return view('kasir.pos', [
            'categories' => $categories,
            'products' => $products,
            'shift' => $request->user()->openShift(),
            'catId' => $request->integer('kategori'),
            'q' => $request->string('q')->toString(),
        ]);
    }

    /** FR-12: simpan transaksi. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:cash,qris'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'customer_name' => ['nullable', 'string', 'max:100'],
        ]);

        $transaction = $this->pos->checkout(
            items: $data['items'],
            paymentMethod: $data['payment_method'],
            paidAmount: (float) $data['paid_amount'],
            customerName: $data['customer_name'] ?? null,
            cashier: $request->user(),
            shift: $request->user()->openShift(),
        );

        return redirect()
            ->route('struk.show', $transaction)
            ->with('fresh', true)
            ->with('success', 'Transaksi '.$transaction->invoice_no.' tersimpan.');
    }

    /** FR-15: riwayat transaksi shift aktif. */
    public function history(Request $request)
    {
        $shift = $request->user()->openShift();

        $query = Transaction::where('shift_id', $shift->id)
            ->search($request->string('q')->toString())
            ->latest();

        $done = (clone $query)->completed()->get();

        return view('kasir.riwayat', [
            'shift' => $shift,
            'transactions' => $query->withCount('items')->paginate(25)->withQueryString(),
            'cash' => (float) $done->where('payment_method', 'cash')->sum('total'),
            'qris' => (float) $done->where('payment_method', 'qris')->sum('total'),
            'cashN' => $done->where('payment_method', 'cash')->count(),
            'qrisN' => $done->where('payment_method', 'qris')->count(),
            'doneN' => $done->count(),
            'q' => $request->string('q')->toString(),
        ]);
    }

    /** Void transaksi milik shift aktif kasir. */
    public function void(Request $request, Transaction $transaction)
    {
        $shift = $request->user()->openShift();

        abort_if($transaction->shift_id !== $shift->id, 403, 'Transaksi bukan milik shift aktif Anda.');

        $data = $request->validate([
            'void_reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['void_reason' => 'alasan void']);

        $this->pos->void($transaction, $data['void_reason'], $request->user());

        return back()->with('success', 'Transaksi '.$transaction->invoice_no.' di-void. Stok telah dikembalikan.');
    }
}
