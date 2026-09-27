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
    public function __construct(private readonly PosService $pos)
    {
    }

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
            'products'   => $products,
            'shift'      => $request->user()->openShift(),
            'catId'      => $request->integer('kategori'),
            'q'          => $request->string('q')->toString(),
        ]);
    }

    /** FR-12: simpan transaksi. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'integer', 'exists:products,id'],
            'items.*.qty'          => ['required', 'integer', 'min:1'],
            'payment_method'       => ['required', 'in:cash,qris'],
            'paid_amount'          => ['required', 'numeric', 'min:0'],
            'customer_name'        => ['nullable', 'string', 'max:100'],
        ]);

        $transaction = $this->pos->checkout(
            items:         $data['items'],
            paymentMethod: $data['payment_method'],
            paidAmount:    (float) $data['paid_amount'],
            customerName:  $data['customer_name'] ?? null,
            cashier:       $request->user(),
            shift:         $request->user()->openShift(),
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
            'shift'        => $shift,
            'transactions' => $query->paginate(25)->withQueryString(),
            'cash'         => (float) $done->where('payment_method', 'cash')->sum('total'),
            'qris'         => (float) $done->where('payment_method', 'qris')->sum('total'),
            'cashN'        => $done->where('payment_method', 'cash')->count(),
            'qrisN'        => $done->where('payment_method', 'qris')->count(),
            'doneN'        => $done->count(),
            'q'            => $request->string('q')->toString(),
        ]);
    }
}
