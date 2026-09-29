<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class FinanceService
{
    /** Ubah input "2026-09" jadi Carbon awal bulan. Fallback ke bulan berjalan. */
    public static function month(?string $input = null): Carbon
    {
        try {
            $month = $input ? Carbon::createFromFormat('Y-m', $input) : now();
        } catch (\Throwable) {
            $month = now();
        }

        return $month->startOfMonth();
    }

    /**
     * Rekap satu bulan kalender penuh (tanggal 1 s.d. akhir bulan).
     *
     * Omzet & HPP dihitung ulang setiap kali dipanggil, jadi transaksi baru
     * dari kasir langsung terpantul ke angka di halaman ini.
     */
    public static function summary(Carbon $month, bool $withPrevious = true): array
    {
        $from = $month->copy()->startOfMonth();
        $to   = $month->copy()->endOfMonth();

        $transactions = Transaction::completed()
            ->whereBetween('created_at', [$from, $to])
            ->with('items')
            ->get();

        $omzet = (float) $transactions->sum('total');
        $hpp   = (float) $transactions->sum(fn ($t) => $t->totalHpp());
        $gross = $omzet - $hpp;

        $expenses = Expense::with(['category', 'recorder', 'relatedUser'])
            ->inMonth($from, $to)
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        $operational = (float) $expenses->filter(fn ($e) => $e->isOperational())->sum('amount');
        $inventory   = (float) $expenses->reject(fn ($e) => $e->isOperational())->sum('amount');
        $net         = $gross - $operational;

        $perCategory = $expenses
            ->groupBy(fn ($e) => $e->category?->name ?? 'Tanpa kategori')
            ->map(fn ($group, $name) => [
                'name'   => $name,
                'type'   => $group->first()->category?->type ?? 'operational',
                'count'  => $group->count(),
                'amount' => (float) $group->sum('amount'),
            ])
            ->sortByDesc('amount')
            ->values();

        return [
            'month'        => $from,
            'from'         => $from,
            'to'           => $to,
            'isRunning'    => $from->isSameMonth(now()),
            'dayOfMonth'   => $from->isSameMonth(now()) ? now()->day : $to->day,
            'daysInMonth'  => $to->day,
            'omzet'        => $omzet,
            'hpp'          => $hpp,
            'grossProfit'  => $gross,
            'operational'  => $operational,
            'inventory'    => $inventory,
            'cashOut'      => $operational + $inventory,
            'netProfit'    => $net,
            'netMargin'    => $omzet > 0 ? $net / $omzet * 100 : 0,
            'count'        => $transactions->count(),
            'expenses'     => $expenses,
            'perCategory'  => $perCategory,
            'missing'      => self::missingCategories($month, $expenses),
            'previous'     => $withPrevious ? self::summary($month->copy()->subMonth(), false) : null,
        ];
    }

    /**
     * Kategori operasional yang ada di bulan lalu tapi belum tercatat bulan ini.
     * Dipakai sebagai pengingat "listrik belum diinput".
     */
    private static function missingCategories(Carbon $month, $current)
    {
        $prev = $month->copy()->subMonth();

        $last = Expense::with('category')
            ->inMonth($prev->copy()->startOfMonth(), $prev->copy()->endOfMonth())
            ->whereHas('category', fn ($q) => $q->where('type', 'operational'))
            ->get()
            ->pluck('category.name')
            ->filter()
            ->unique();

        $now = $current->filter(fn ($e) => $e->isOperational())
            ->pluck('category.name')
            ->filter()
            ->unique();

        return $last->diff($now)->values();
    }

    /** Daftar bulan untuk dropdown: dari transaksi paling awal s.d. bulan ini. */
    public static function availableMonths(): array
    {
        $first = Transaction::min('created_at');
        $start = $first ? Carbon::parse($first)->startOfMonth() : now()->startOfMonth();
        $start = $start->min(now()->startOfMonth()->subMonths(11));

        $months = [];
        $cursor = now()->startOfMonth();

        while ($cursor->gte($start)) {
            $months[$cursor->format('Y-m')] = $cursor->translatedFormat('F Y');
            $cursor = $cursor->copy()->subMonth();
        }

        return $months;
    }
}