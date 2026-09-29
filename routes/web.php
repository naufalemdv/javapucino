<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\MaterialController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\Kasir\PosController;
use App\Http\Controllers\Kasir\QueueController;
use App\Http\Controllers\Kasir\ShiftController;
use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ShiftReportController;

/*
|--------------------------------------------------------------------------
| Rute Publik
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => redirect()->route('menu'));
Route::get('/menu', [BoardController::class, 'index'])->name('menu');
Route::get('/menu/antrian', [BoardController::class, 'queue'])->name('menu.queue');

/*
|--------------------------------------------------------------------------
| Autentikasi (FR-01, FR-03)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Struk (kasir & admin)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/struk/{transaction}', [ReceiptController::class, 'show'])->name('struk.show');
    Route::get('/struk/{transaction}/cetak', [ReceiptController::class, 'print'])->name('struk.print');
});

/*
|--------------------------------------------------------------------------
| Portal Kasir
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:kasir'])->prefix('kasir')->name('kasir.')->group(function () {
    // FR-02 buka shift (tanpa middleware shift.open)
    Route::get('/shift', [ShiftController::class, 'create'])->name('shift.create');
    Route::post('/shift', [ShiftController::class, 'store'])->name('shift.store');

    Route::middleware('shift.open')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('pos');
        Route::post('/transaksi', [PosController::class, 'store'])->name('transaksi.store');
        Route::get('/riwayat', [PosController::class, 'history'])->name('riwayat');

        // FR-16 tutup shift
        Route::get('/tutup-shift', [ShiftController::class, 'edit'])->name('shift.edit');
        Route::put('/tutup-shift', [ShiftController::class, 'update'])->name('shift.update');

        // Antrian
        Route::get('/antrian', [QueueController::class, 'index'])->name('antrian');
        Route::post('/antrian/berikutnya', [QueueController::class, 'next'])->name('antrian.next');
        Route::post('/antrian/selesai', [QueueController::class, 'picked'])->name('antrian.picked');
        Route::post('/antrian/{transaction}/panggil', [QueueController::class, 'call'])->name('antrian.call');
    });
});

/*
|--------------------------------------------------------------------------
| CMS Admin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Data master
    Route::resource('products', ProductController::class)->except('show')->parameters(['products' => 'product']);
    Route::resource('categories', CategoryController::class)->except('show');
    Route::resource('materials', MaterialController::class)->except('show');
    Route::post('materials/{material}/restock', [MaterialController::class, 'restock'])->name('materials.restock');
    Route::resource('users', UserController::class)->except('show');
    Route::post('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');

    // Transaksi & void
    Route::get('/transaksi', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transaksi/export', [TransactionController::class, 'export'])->name('transactions.export');
    Route::get('/transaksi/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::post('/transaksi/{transaction}/void', [TransactionController::class, 'void'])->name('transactions.void');

    // Laporan, audit, pengaturan
    Route::get('/laporan', [ReportController::class, 'index'])->name('report');
    Route::get('/laporan/export', [ReportController::class, 'export'])->name('report.export');
        // Rekap shift & selisih kas (FR-28)
    Route::get('/shift', [ShiftReportController::class, 'index'])->name('shifts');
    Route::get('/shift/export', [ShiftReportController::class, 'export'])->name('shifts.export');
    
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit');
    

    // Pengeluaran & laba bersih (FR-27) — rute statis didaftarkan sebelum resource
    Route::get('/pengeluaran/export', [ExpenseController::class, 'export'])->name('expenses.export');
    Route::get('/pengeluaran/kategori', [ExpenseCategoryController::class, 'index'])->name('expenses.categories');
    Route::post('/pengeluaran/kategori', [ExpenseCategoryController::class, 'store'])->name('expenses.categories.store');
    Route::put('/pengeluaran/kategori/{category}', [ExpenseCategoryController::class, 'update'])->name('expenses.categories.update');
    Route::delete('/pengeluaran/kategori/{category}', [ExpenseCategoryController::class, 'destroy'])->name('expenses.categories.destroy');
    Route::resource('pengeluaran', ExpenseController::class)
        ->except('show')
        ->parameters(['pengeluaran' => 'expense'])
        ->names('expenses');

    Route::get('/pengaturan', [SettingController::class, 'index'])->name('settings');
    Route::put('/pengaturan', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/pengaturan/slide', [SettingController::class, 'slideStore'])->name('settings.slides.store');
    Route::patch('/pengaturan/slide/{index}', [SettingController::class, 'slideMove'])->whereNumber('index')->name('settings.slides.move');
    Route::delete('/pengaturan/slide/{index}', [SettingController::class, 'slideDestroy'])->whereNumber('index')->name('settings.slides.destroy');

    // Antrian versi admin
    Route::get('/antrian', [QueueController::class, 'index'])->name('antrian');
    Route::post('/antrian/berikutnya', [QueueController::class, 'next'])->name('antrian.next');
    Route::post('/antrian/selesai', [QueueController::class, 'picked'])->name('antrian.picked');
    Route::post('/antrian/{transaction}/panggil', [QueueController::class, 'call'])->name('antrian.call');
});