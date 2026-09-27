<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    private const RUPIAH = '"Rp" #,##0';

    /** FR-24 */
    public function index(Request $request)
    {
        return view('admin.report', $this->reportData($request));
    }

    /** Export laporan ke Excel (.xlsx) sesuai filter periode & kasir yang aktif. */
    public function export(Request $request)
    {
        $r = $this->reportData($request);

        $book = new Spreadsheet();
        $book->getProperties()->setCreator(setting('store_name', 'Javapucino'))->setTitle('Laporan Penjualan');

        // --- Ringkasan
        $sh = $book->getActiveSheet()->setTitle('Ringkasan');
        $sh->fromArray([
            ['Laporan Penjualan '.setting('store_name', 'Javapucino')],
            [],
            ['Periode', $r['from']->format('d/m/Y').' – '.$r['to']->format('d/m/Y').' ('.$r['days'].' hari)'],
            ['Kasir', $r['cashier']?->name ?? 'Semua kasir'],
            ['Dibuat', now()->format('d/m/Y H:i')],
            [],
            ['Omzet', $r['omzet']],
            ['Jumlah transaksi', $r['count']],
            ['HPP', $r['hpp']],
            ['Laba kotor', $r['omzet'] - $r['hpp']],
            ['Margin', $r['omzet'] > 0 ? ($r['omzet'] - $r['hpp']) / $r['omzet'] : 0],
            ['Rata-rata harian', $r['omzet'] / $r['days']],
        ], null, 'A1', true);
        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sh->getStyle('A3:A12')->getFont()->setBold(true);
        foreach (['B7', 'B9', 'B10', 'B12'] as $c) {
            $sh->getStyle($c)->getNumberFormat()->setFormatCode(self::RUPIAH);
        }
        $sh->getStyle('B11')->getNumberFormat()->setFormatCode('0%');
        $sh->getStyle('B7:B12')->getAlignment()->setHorizontal('left');
        $this->autosize($sh, 'A', 'B');

        // --- Omzet harian
        $this->table($book, 'Harian', ['Tanggal', 'Omzet'],
            $r['perDay']->map(fn ($d) => [$d['date']->format('d/m/Y'), $d['value']])->all(),
            ['B' => self::RUPIAH], ['Total', $r['omzet']]);

        // --- Penjualan per menu
        $this->table($book, 'Per Menu', ['Menu', 'Terjual', 'Omzet', 'HPP', 'Laba kotor', 'Margin'],
            $r['rows']->map(function ($row) {
                $rev = (float) $row->revenue;
                $cost = (float) $row->cost;

                return [$row->product_name, (int) $row->qty, $rev, $cost, $rev - $cost, $rev > 0 ? ($rev - $cost) / $rev : 0];
            })->all(),
            ['C' => self::RUPIAH, 'D' => self::RUPIAH, 'E' => self::RUPIAH, 'F' => '0%'],
            ['Total', (int) $r['rows']->sum('qty'), $r['omzet'], $r['hpp'], $r['omzet'] - $r['hpp'], null]);

        // --- Penjualan per kasir
        $this->table($book, 'Per Kasir', ['Kasir', 'Transaksi', 'Omzet', 'Kontribusi'],
            $r['perCashier']->map(fn ($c) => [$c['name'], $c['count'], $c['omzet'], $r['omzet'] > 0 ? $c['omzet'] / $r['omzet'] : 0])->all(),
            ['C' => self::RUPIAH, 'D' => '0%'], ['Total', $r['count'], $r['omzet'], null]);

        // --- Daftar transaksi
        $this->table($book, 'Transaksi', ['No. Invoice', 'Tanggal', 'Jam', 'Kasir', 'Pelanggan', 'Metode', 'Item', 'Subtotal', 'Pajak', 'Total'],
            $r['transactions']->sortBy('created_at')->map(fn ($t) => [
                $t->invoice_no,
                $t->created_at->format('d/m/Y'),
                $t->created_at->format('H:i'),
                $t->cashier?->name ?? 'Kasir terhapus',
                $t->customer_name,
                $t->paymentLabel(),
                $t->totalQty(),
                (float) $t->subtotal,
                (float) $t->tax_amount,
                (float) $t->total,
            ])->values()->all(),
            ['H' => self::RUPIAH, 'I' => self::RUPIAH, 'J' => self::RUPIAH],
            ['Total', null, null, null, null, null, null, (float) $r['transactions']->sum('subtotal'), (float) $r['transactions']->sum('tax_amount'), $r['omzet']]);

        $book->setActiveSheetIndex(0);

        $name = 'laporan-'.$r['from']->format('Ymd').'-'.$r['to']->format('Ymd')
            .($r['cashier'] ? '-'.str($r['cashier']->name)->slug() : '').'.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** Satu sheet tabel: header merah, format kolom, baris total tebal. */
    private function table(Spreadsheet $book, string $title, array $head, array $rows, array $formats, ?array $total = null): void
    {
        $sh   = $book->createSheet()->setTitle($title);
        $last = chr(ord('A') + count($head) - 1);
        $end  = count($rows) + 1;

        $sh->fromArray($head, null, 'A1');
        if ($rows) {
            $sh->fromArray($rows, null, 'A2', true);
        }
        if ($total) {
            $end++;
            $sh->fromArray($total, null, 'A'.$end, true);
            $sh->getStyle("A{$end}:{$last}{$end}")->getFont()->setBold(true);
            $sh->getStyle("A{$end}:{$last}{$end}")->getBorders()->getTop()->setBorderStyle('thin');
        }

        $sh->getStyle("A1:{$last}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sh->getStyle("A1:{$last}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E31E24');
        foreach ($formats as $col => $fmt) {
            $sh->getStyle("{$col}2:{$col}{$end}")->getNumberFormat()->setFormatCode($fmt);
        }
        $sh->freezePane('A2');
        $this->autosize($sh, 'A', $last);
    }

    private function autosize(Worksheet $sh, string $from, string $to): void
    {
        foreach (range($from, $to) as $col) {
            $sh->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /** Data laporan untuk halaman & export, berdasarkan filter periode + kasir. */
    private function reportData(Request $request): array
    {
        $request->validate([
            'from'  => ['nullable', 'date_format:Y-m-d'],
            'to'    => ['nullable', 'date_format:Y-m-d'],
            'kasir' => ['nullable', 'integer'],
        ]);

        // Filter kasir: kosong = semua kasir
        $cashierId = $request->integer('kasir') ?: null;
        if ($cashierId && ! User::withTrashed()->whereKey($cashierId)->exists()) {
            $cashierId = null;
        }
        $byCashier = fn ($q) => $q->when($cashierId, fn ($x) => $x->where('user_id', $cashierId));

        if ($request->filled('from') || $request->filled('to')) {
            // Rentang kustom dari kalender
            $range = null;
            $from  = Carbon::parse($request->input('from', $request->input('to')))->startOfDay();
            $to    = Carbon::parse($request->input('to', $request->input('from')))->endOfDay();

            if ($from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }

            // Batasi 1 tahun agar grafik & query tetap ringan
            if ($from->diffInDays($to) > 366) {
                $from = $to->copy()->subDays(365)->startOfDay();
            }
        } else {
            $range = (int) $request->input('range', 7);
            $range = in_array($range, [7, 14, 30], true) ? $range : 7;

            $from = now()->subDays($range - 1)->startOfDay();
            $to   = now()->endOfDay();
        }

        $days = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;

        $transactions = Transaction::completed()
            ->whereBetween('created_at', [$from, $to])
            ->tap($byCashier)
            ->with(['items', 'cashier' => fn ($q) => $q->withTrashed()])
            ->get();

        $omzet = (float) $transactions->sum('total');
        $hpp   = (float) $transactions->sum(fn ($t) => $t->totalHpp());

        $perDay = collect(range(0, $days - 1))->map(function ($i) use ($from, $transactions) {
            $date = $from->copy()->addDays($i);

            return [
                'date'  => $date,
                'value' => (float) $transactions
                    ->filter(fn ($t) => $t->created_at->isSameDay($date))
                    ->sum('total'),
            ];
        });

        $rows = TransactionItem::query()
            ->select(
                'product_name',
                DB::raw('SUM(qty) as qty'),
                DB::raw('SUM(subtotal) as revenue'),
                DB::raw('SUM(hpp * qty) as cost'),
            )
            ->whereHas('transaction', fn ($q) => $q->completed()->whereBetween('created_at', [$from, $to])->tap($byCashier))
            ->groupBy('product_name')
            ->orderByDesc('revenue')
            ->get();

        // Rekap per kasir pada periode terpilih
        $perCashier = $transactions
            ->groupBy('user_id')
            ->map(fn ($list, $id) => [
                'id'    => $id,
                'name'  => $list->first()->cashier?->name ?? 'Kasir terhapus',
                'count' => $list->count(),
                'omzet' => (float) $list->sum('total'),
            ])
            ->sortByDesc('omzet')
            ->values();

        // Pilihan kasir: semua akun kasir + siapa pun yang pernah bertransaksi
        $cashiers = User::withTrashed()
            ->where(fn ($q) => $q->where('role', 'kasir')->orWhereIn('id', Transaction::select('user_id')->distinct()))
            ->orderBy('name')
            ->get(['id', 'name', 'deleted_at']);

        return [
            'cashierId'    => $cashierId,
            'cashier'      => $cashierId ? $cashiers->firstWhere('id', $cashierId) : null,
            'cashiers'     => $cashiers,
            'perCashier'   => $perCashier,
            'days'         => $days,
            'range'        => $range,
            'from'         => $from,
            'to'           => $to,
            'omzet'        => $omzet,
            'hpp'          => $hpp,
            'count'        => $transactions->count(),
            'transactions' => $transactions,
            'perDay'       => $perDay,
            'rows'         => $rows,
        ];
    }
}
