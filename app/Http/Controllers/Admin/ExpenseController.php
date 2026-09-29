<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExpenseController extends Controller
{
    private const RUPIAH = '"Rp" #,##0';

    /** FR-27: rekap laba bersih bulanan + daftar pengeluaran. */
    public function index(Request $request)
    {
        $month = FinanceService::month($request->string('bulan')->toString() ?: null);
        $data  = FinanceService::summary($month);

        $q    = $request->string('q')->toString();
        $cat  = $request->integer('kategori') ?: null;
        $list = $data['expenses']
            ->when($cat, fn ($c) => $c->where('expense_category_id', $cat))
            ->when($q, fn ($c) => $c->filter(
                fn ($e) => str_contains(mb_strtolower($e->title.' '.$e->note), mb_strtolower($q))
            ))
            ->values();

        return view('admin.expenses.index', $data + [
            'list'       => $list,
            'q'          => $q,
            'catId'      => $cat,
            'categories' => ExpenseCategory::ordered()->get(),
            'months'     => FinanceService::availableMonths(),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.expenses.form', [
            'expense'    => new Expense([
                'expense_date'   => now()->toDateString(),
                'payment_method' => 'cash',
            ]),
            'categories' => ExpenseCategory::active()->operational()->ordered()->get(),
            'staff'      => User::orderBy('name')->get(['id', 'name']),
            'bulan'      => $request->string('bulan')->toString(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;

        $expense = Expense::create($data);

        AuditLogger::log(
            'create',
            'Pengeluaran "'.$expense->title.'" '.rupiah($expense->amount).' dicatat',
            $expense,
            null,
            $expense->only(['title', 'amount', 'expense_category_id', 'expense_date']),
        );

        return redirect()
            ->route('admin.expenses.index', ['bulan' => $expense->expense_date->format('Y-m')])
            ->with('success', 'Pengeluaran tersimpan.');
    }

    public function edit(Expense $expense)
    {
        $this->guardLocked($expense);

        return view('admin.expenses.form', [
            'expense'    => $expense,
            'categories' => ExpenseCategory::active()->operational()->ordered()->get(),
            'staff'      => User::orderBy('name')->get(['id', 'name']),
            'bulan'      => $expense->expense_date->format('Y-m'),
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        $this->guardLocked($expense);

        $old = $expense->only(['title', 'amount', 'expense_category_id', 'expense_date']);
        $expense->update($this->validated($request));

        AuditLogger::log(
            'update',
            'Pengeluaran "'.$expense->title.'" diperbarui',
            $expense,
            $old,
            $expense->only(['title', 'amount', 'expense_category_id', 'expense_date']),
        );

        return redirect()
            ->route('admin.expenses.index', ['bulan' => $expense->expense_date->format('Y-m')])
            ->with('success', 'Perubahan tersimpan.');
    }

    public function destroy(Expense $expense)
    {
        $this->guardLocked($expense);

        $month = $expense->expense_date->format('Y-m');
        $expense->delete();

        AuditLogger::log(
            'delete',
            'Pengeluaran "'.$expense->title.'" '.rupiah($expense->amount).' dihapus',
            $expense,
        );

        return redirect()
            ->route('admin.expenses.index', ['bulan' => $month])
            ->with('success', 'Pengeluaran dihapus.');
    }

    /** Export .xlsx: sheet Rekap + sheet Rincian. */
    public function export(Request $request)
    {
        $month = FinanceService::month($request->string('bulan')->toString() ?: null);
        $r     = FinanceService::summary($month, false);

        $book = new Spreadsheet();
        $book->getProperties()
            ->setCreator(setting('store_name', 'Javapucino'))
            ->setTitle('Laporan Keuangan '.$month->translatedFormat('F Y'));

        // --- Sheet 1: Rekap
        $sh = $book->getActiveSheet()->setTitle('Rekap');
        $sh->fromArray([
            ['Laporan Keuangan '.setting('store_name', 'Javapucino')],
            [],
            ['Periode', $r['from']->format('d/m/Y').' – '.$r['to']->format('d/m/Y')],
            ['Status', $r['isRunning'] ? 'Bulan berjalan (belum selesai)' : 'Bulan selesai'],
            ['Dibuat', now()->format('d/m/Y H:i')],
            [],
            ['Omzet', $r['omzet']],
            ['Jumlah transaksi', $r['count']],
            ['HPP', $r['hpp']],
            ['Laba kotor', $r['grossProfit']],
            ['Biaya operasional', $r['operational']],
            ['Laba bersih', $r['netProfit']],
            ['Margin bersih', $r['omzet'] > 0 ? $r['netProfit'] / $r['omzet'] : 0],
            [],
            ['Pembelian bahan (tidak mengurangi laba)', $r['inventory']],
            ['Total kas keluar', $r['cashOut']],
        ], null, 'A1', true);

        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sh->getStyle('A3:A16')->getFont()->setBold(true);
        $sh->getStyle('A12:B12')->getFont()->setBold(true);
        foreach (['B7', 'B9', 'B10', 'B11', 'B12', 'B15', 'B16'] as $cell) {
            $sh->getStyle($cell)->getNumberFormat()->setFormatCode(self::RUPIAH);
        }
        $sh->getStyle('B13')->getNumberFormat()->setFormatCode('0%');
        $sh->getStyle('B7:B16')->getAlignment()->setHorizontal('left');
        foreach (['A', 'B'] as $col) {
            $sh->getColumnDimension($col)->setAutoSize(true);
        }

        // --- Sheet 2: Per kategori
        $this->sheet($book, 'Per Kategori',
            ['Kategori', 'Jenis', 'Jumlah entri', 'Total'],
            $r['perCategory']->map(fn ($c) => [
                $c['name'],
                $c['type'] === 'inventory' ? 'Pembelian Stok' : 'Operasional',
                $c['count'],
                $c['amount'],
            ])->all(),
            ['D' => self::RUPIAH],
            ['Total', null, $r['expenses']->count(), $r['cashOut']],
        );

        // --- Sheet 3: Rincian
        $this->sheet($book, 'Rincian',
            ['Tanggal', 'Kategori', 'Jenis', 'Keterangan', 'Untuk', 'Metode', 'Nominal', 'Dicatat oleh'],
            $r['expenses']->sortBy('expense_date')->map(fn ($e) => [
                $e->expense_date->format('d/m/Y'),
                $e->category?->name ?? '-',
                $e->isOperational() ? 'Operasional' : 'Pembelian Stok',
                $e->title,
                $e->relatedUser?->name,
                $e->paymentLabel(),
                (float) $e->amount,
                $e->recorder?->name,
            ])->values()->all(),
            ['G' => self::RUPIAH],
            ['Total', null, null, null, null, null, $r['cashOut'], null],
        );

        $book->setActiveSheetIndex(0);
        $name = 'pengeluaran-'.$month->format('Y-m').'.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** Satu sheet tabel: header merah, baris total tebal. */
    private function sheet(Spreadsheet $book, string $title, array $head, array $rows, array $formats, ?array $total = null): void
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
        foreach (range('A', $last) as $col) {
            $sh->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /** BR-20 */
    private function guardLocked(Expense $expense): void
    {
        abort_if(
            $expense->isLocked(),
            403,
            'Pengeluaran pembelian bahan hanya dapat dikoreksi dari menu Stok Bahan.'
        );
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'expense_category_id' => [
                'required',
                Rule::exists('expense_categories', 'id')
                    ->where('type', 'operational')
                    ->whereNull('deleted_at'),
            ],
            'expense_date'   => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'title'          => ['required', 'string', 'max:120'],
            'amount'         => ['required', 'numeric', 'min:0.01', 'max:9999999999'], // BR-15
            'payment_method' => ['required', 'in:cash,transfer,qris'],
            'related_user_id' => ['nullable', 'exists:users,id'],
            'note'           => ['nullable', 'string', 'max:500'],
        ], [], [
            'expense_category_id' => 'kategori',
            'expense_date'        => 'tanggal',
            'title'               => 'keterangan',
            'amount'              => 'nominal',
            'payment_method'      => 'metode pembayaran',
            'related_user_id'     => 'untuk karyawan',
            'note'                => 'catatan',
        ]);
    }
}