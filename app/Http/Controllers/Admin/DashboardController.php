<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** FR-17 */
    public function index()
    {
        $today = Transaction::today()->completed()->with('items')->get();

        $omzet = (float) $today->sum('total');
        $hpp   = (float) $today->sum(fn ($t) => $t->totalHpp());
        $items = (int) $today->sum(fn ($t) => $t->totalQty());
        $cashN = $today->where('payment_method', 'cash')->count();
        $qrisN = $today->where('payment_method', 'qris')->count();

        // grafik per jam 08:00 - 21:00
        $hours  = range(8, 21);
        $byHour = collect($hours)->map(fn ($h) => [
            'hour'  => $h,
            'value' => (float) $today->filter(fn ($t) => (int) $t->created_at->format('H') === $h)->sum('total'),
        ]);

        // menu terlaris hari ini
        $top = TransactionItem::query()
            ->select('product_name', DB::raw('SUM(qty) as qty'))
            ->whereHas('transaction', fn ($q) => $q->today()->completed())
            ->groupBy('product_name')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();

        $threshold = (int) setting('low_stock_threshold', 5);

        $lowProducts = Product::active()->where('stock', '<=', $threshold)->orderBy('stock')->get()
            ->map(fn ($p) => ['name' => $p->name, 'info' => $p->stock.' porsi', 'out' => $p->stock <= 0]);

        $lowMaterials = Material::whereColumn('stock', '<', 'min_stock')->get()
            ->map(fn ($m) => ['name' => $m->name, 'info' => angka($m->stock).' '.$m->unit, 'out' => false]);

        return view('admin.dashboard', [
            'omzet'   => $omzet,
            'hpp'     => $hpp,
            'items'   => $items,
            'count'   => $today->count(),
            'avg'     => $today->count() ? $omzet / $today->count() : 0,
            'cashN'   => $cashN,
            'qrisN'   => $qrisN,
            'cashSum' => (float) $today->where('payment_method', 'cash')->sum('total'),
            'qrisSum' => (float) $today->where('payment_method', 'qris')->sum('total'),
            'byHour'  => $byHour,
            'top'     => $top,
            'low'     => $lowProducts->concat($lowMaterials),
            'latest'  => Transaction::with('cashier')->latest()->limit(6)->get(),
            'variety' => $today->flatMap(fn ($t) => $t->items->pluck('product_name'))->unique()->count(),
        ]);
    }
}
