<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ShiftReportController extends Controller
{
    private const RUPIAH = '"Rp" #,##0';

    /** FR-28: rekap shift & rekonsiliasi kas. */
    public function index(Request $request)
    {
        return view('admin.shifts', $this->reportData($request));
    }

    public function export(Request $request)
    {
        $r = $this->reportData($request);

        $book = new Spreadsheet();
        $book->getProperties()
            ->setCreator(setting('store_name', 'Javapucino'))
            ->setTitle('Rekap Shift & Selisih Kas');

        // --- Ringkasan
        $sh = $book->getActiveSheet()->setTitle('Ringkasan');
        $sh->fromArray([
            ['Rekap Shift & Selisih Kas '.setting('store_name', 'Javapucino')],
            [],
            ['Periode', $r['from']->format('d/m/Y').' – '.$r['to']->format('d/m/Y')],
            ['Kasir', $r['cashier']?->name ?? 'Semua kasir'],
            ['Dibuat', now()->format('d/m/Y H:i')],
            [],
            ['Jumlah shift', $r['shifts']->count()],
            ['Shift masih berjalan', $r['openCount']],
            ['Penjualan tunai', $r['cashTotal']],
            ['Penjualan QRIS', $r['qrisTotal']],
            ['Akumulasi selisih kas', $r['gapTotal']],
            ['Shift dengan selisih', $r['gapCount']],
        ], null, 'A1', true);

        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sh->getStyle('A3:A12')->getFont()->setBold(true);
        foreach (['B9', 'B10', 'B11'] as $cell) {
            $sh->getStyle($cell)->getNumberFormat()->setFormatCode(self::RUPIAH);
        }
        $sh->getStyle('B7:B12')->getAlignment()->setHorizontal('left');
        $this->autosize($sh, 'A', 'B');

        // --- Per kasir
        $this->table($book, 'Per Kasir',
            ['Kasir', 'Shift', 'Penjualan tunai', 'Total selisih', 'Rata-rata selisih'],
            $r['perCashier']->map(fn ($c) => [
                $c['name'], $c['shifts'], $c['cash'], $c['gap'], $c['gapAvg'],
            ])->all(),
            ['C' => self::RUPIAH, 'D' => self::RUPIAH, 'E' => self::RUPIAH],
            ['Total', $r['shifts']->count(), $r['cashTotal'], $r['gapTotal'], null],
        );

        // --- Rincian shift
        $this->table($book, 'Shift',
            ['Tanggal', 'Kasir', 'Shift', 'Buka', 'Tutup', 'Transaksi', 'Modal awal',
             'Penjualan tunai', 'Kas seharusnya', 'Kas aktual', 'Selisih', 'Penjualan QRIS', 'Status', 'Catatan'],
            $r['shifts']->sortBy('opened_at')->map(fn ($s) => [
                $s->opened_at->format('d/m/Y'),
                $s->user?->name ?? 'Kasir terhapus',
                ucfirst($s->shift_type),
                $s->opened_at->format('H:i'),
                $s->closed_at?->format('H:i'),
                (int) $s->trx_count,
                (float) $s->opening_cash,
                (float) $s->cash_sales,
                (float) $s->expected,
                $s->isOpen() ? null : (float) $s->actual_cash,
                $s->gap,
                (float) $s->qris_sales,
                $s->isOpen() ? 'Berjalan' : 'Ditutup',
                $s->note,
            ])->values()->all(),
            ['G' => self::RUPIAH, 'H' => self::RUPIAH, 'I' => self::RUPIAH,
             'J' => self::RUPIAH, 'K' => self::RUPIAH, 'L' => self::RUPIAH],
            ['Total', null, null, null, null, (int) $r['shifts']->sum('trx_count'),
             (float) $r['shifts']->sum('opening_cash'), $r['cashTotal'], null, null,
             $r['gapTotal'], $r['qrisTotal'], null, null],
        );

        $book->setActiveSheetIndex(0);
        $name = 'rekap-shift-'.$r['from']->format('Ymd').'-'.$r['to']->format('Ymd')
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

    /**
     * Data untuk halaman & export.
     *
     * Kas seharusnya: shift yang sudah ditutup memakai nilai tersimpan
     * (expected_cash) agar angka historis tidak berubah; shift yang masih
     * berjalan dihitung langsung dari modal awal + penjualan tunai.
     */
    private function reportData(Request $request): array
    {
        $request->validate([
            'from'  => ['nullable', 'date_format:Y-m-d'],
            'to'    => ['nullable', 'date_format:Y-m-d'],
            'kasir' => ['nullable', 'integer'],
        ]);

        $cashierId = $request->integer('kasir') ?: null;
        if ($cashierId && ! User::withTrashed()->whereKey($cashierId)->exists()) {
            $cashierId = null;
        }

        if ($request->filled('from') || $request->filled('to')) {
            $range = null;
            $from  = Carbon::parse($request->input('from', $request->input('to')))->startOfDay();
            $to    = Carbon::parse($request->input('to', $request->input('from')))->endOfDay();

            if ($from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }
            if ($from->diffInDays($to) > 366) {
                $from = $to->copy()->subDays(365)->startOfDay();
            }
        } else {
            $range = (int) $request->input('range', 7);
            $range = in_array($range, [7, 14, 30], true) ? $range : 7;
            $from  = now()->subDays($range - 1)->startOfDay();
            $to    = now()->endOfDay();
        }

        $shifts = Shift::query()
            ->with(['user' => fn ($q) => $q->withTrashed()])
            ->withSum(['completedTransactions as cash_sales' => fn ($q) => $q->where('payment_method', 'cash')], 'total')
            ->withSum(['completedTransactions as qris_sales' => fn ($q) => $q->where('payment_method', 'qris')], 'total')
            ->withCount('completedTransactions as trx_count')
            ->whereBetween('opened_at', [$from, $to])
            ->when($cashierId, fn ($q) => $q->where('user_id', $cashierId))
            ->orderByDesc('opened_at')
            ->get()
            ->each(function ($s) {
                $s->cash_sales = (float) ($s->cash_sales ?? 0);
                $s->qris_sales = (float) ($s->qris_sales ?? 0);
                $s->expected   = $s->isOpen()
                    ? (float) $s->opening_cash + $s->cash_sales
                    : (float) $s->expected_cash;
                $s->gap        = $s->isOpen() ? null : (float) $s->difference;
            });

        $closed = $shifts->reject(fn ($s) => $s->isOpen());

        $perCashier = $shifts
            ->groupBy('user_id')
            ->map(function ($list) {
                $done = $list->reject(fn ($s) => $s->isOpen());

                return [
                    'id'     => $list->first()->user_id,
                    'name'   => $list->first()->user?->name ?? 'Kasir terhapus',
                    'shifts' => $list->count(),
                    'cash'   => (float) $list->sum('cash_sales'),
                    'gap'    => (float) $done->sum('gap'),
                    'gapAvg' => $done->count() ? (float) $done->sum('gap') / $done->count() : 0.0,
                ];
            })
            ->sortBy('gap')
            ->values();

        $cashiers = User::withTrashed()
            ->where(fn ($q) => $q->where('role', 'kasir')->orWhereIn('id', Shift::select('user_id')->distinct()))
            ->orderBy('name')
            ->get(['id', 'name', 'deleted_at']);

        return [
            'range'      => $range,
            'from'       => $from,
            'to'         => $to,
            'cashierId'  => $cashierId,
            'cashier'    => $cashierId ? $cashiers->firstWhere('id', $cashierId) : null,
            'cashiers'   => $cashiers,
            'shifts'     => $shifts,
            'perCashier' => $perCashier,
            'cashTotal'  => (float) $shifts->sum('cash_sales'),
            'qrisTotal'  => (float) $shifts->sum('qris_sales'),
            'gapTotal'   => (float) $closed->sum('gap'),
            'gapCount'   => $closed->filter(fn ($s) => abs((float) $s->gap) > 0.009)->count(),
            'openCount'  => $shifts->filter(fn ($s) => $s->isOpen())->count(),
        ];
    }
}