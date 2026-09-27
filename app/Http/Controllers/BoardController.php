<?php

namespace App\Http\Controllers;

use App\Models\Transaction;

/**
 * FR-04: Menu board / layar pelanggan (publik, tanpa login).
 */
class BoardController extends Controller
{
    public function index()
    {
        $slides = collect(board_slides())->map(fn ($p) => asset('storage/'.$p))->all() ?: [asset('images/daftar-menu.jpg')];

        return view('board.index', [
            'queue'   => $this->queueState(),
            'slides'  => $slides,
            'slideMs' => max(3, (int) setting('board_slide_seconds', 6)) * 1000,
        ]);
    }

    /** Dipanggil berkala oleh layar pelanggan (polling JSON). */
    public function queue()
    {
        return response()->json($this->queueState());
    }

    private function queueState(): array
    {
        $today = Transaction::today()->completed()->whereNotNull('queue_no');

        $current = (clone $today)->where('queue_status', 'called')->latest('updated_at')->first();
        $waiting = (clone $today)->where('queue_status', 'waiting')->orderBy('queue_no')->get();
        $doneN   = (clone $today)->where('queue_status', 'done')->count();

        return [
            'store'   => setting('store_name'),
            'current' => $current ? [
                'no'   => queue_no($current->queue_no),
                'name' => $current->customer_name,
            ] : null,
            'next'    => $waiting->take(8)->map(fn ($t) => [
                'no'   => queue_no($t->queue_no),
                'name' => $t->customer_name,
            ])->values(),
            'waiting' => $waiting->count(),
            'done'    => $doneN,
            // Berubah bila admin mengganti gambar/durasi slide -> layar memuat ulang sendiri
            'slides_v' => md5(setting('board_slides', '[]').'|'.setting('board_slide_seconds', 6)),
        ];
    }
}
