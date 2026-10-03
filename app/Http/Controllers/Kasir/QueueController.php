<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

/**
 * Kontrol antrian: kasir/admin memanggil nomor yang tampil di layar pelanggan.
 */
class QueueController extends Controller
{
    public function index()
    {
        $today = Transaction::today()->completed()->whereNotNull('queue_no')->with('items');

        return view('kasir.antrian', [
            'current' => (clone $today)->where('queue_status', 'called')->latest('updated_at')->first(),
            'waiting' => (clone $today)->where('queue_status', 'waiting')->orderBy('queue_no')->get(),
            'done'    => (clone $today)->where('queue_status', 'done')->orderByDesc('queue_no')->limit(12)->get(),
        ]);
    }

    public function next()
    {
        $next = Transaction::today()->completed()
            ->where('queue_status', 'waiting')
            ->orderBy('queue_no')->first();

        if (! $next) {
            return back()->with('info', 'Tidak ada antrian yang menunggu.');
        }

        $this->markCurrentDone($next->id);
        $next->update(['queue_status' => 'called']);

        AuditLogger::log('update', 'Panggil antrian '.$next->queueLabel().' ('.$next->invoice_no.')', $next);

        return back()->with('success', 'Antrian '.$next->queueLabel().' dipanggil.');
    }

    public function call(Transaction $transaction)
    {
        $this->markCurrentDone($transaction->id);
        $transaction->update(['queue_status' => 'called']);

        AuditLogger::log('update', 'Panggil antrian '.$transaction->queueLabel().' ('.$transaction->invoice_no.')', $transaction);

        return back()->with('success', 'Antrian '.$transaction->queueLabel().' dipanggil.');
    }

    public function picked()
    {
        $current = Transaction::today()->where('queue_status', 'called')->latest('updated_at')->first();

        if (! $current) {
            return back()->with('info', 'Belum ada antrian yang dipanggil.');
        }

        $current->update(['queue_status' => 'done']);

        AuditLogger::log('update', 'Antrian '.$current->queueLabel().' selesai diambil', $current);

        return back()->with('success', 'Antrian '.$current->queueLabel().' selesai.');
    }

    private function markCurrentDone(int $exceptId): void
    {
        Transaction::today()->where('queue_status', 'called')
            ->where('id', '!=', $exceptId)
            ->update(['queue_status' => 'done']);
    }
}
