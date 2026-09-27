<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    /** FR-02: form buka shift. */
    public function create(Request $request)
    {
        if ($shift = $request->user()->openShift()) {
            return redirect()->route('kasir.pos');
        }

        return view('kasir.shift-open');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'shift_type'   => ['required', 'in:pagi,sore'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ]);

        // BR-07: satu kasir hanya boleh punya satu shift open
        if ($request->user()->openShift()) {
            return redirect()->route('kasir.pos');
        }

        $shift = Shift::create([
            'user_id'      => $request->user()->id,
            'shift_type'   => $data['shift_type'],
            'opened_at'    => now(),
            'opening_cash' => $data['opening_cash'],
            'status'       => 'open',
        ]);

        AuditLogger::log('create', 'Buka shift '.$shift->shift_type.' · modal '.rupiah($shift->opening_cash), $shift);

        return redirect()->route('kasir.pos')->with('success', 'Shift dibuka. Selamat bekerja!');
    }

    /** FR-16: halaman tutup shift. */
    public function edit(Request $request)
    {
        $shift = $request->user()->openShift();
        abort_unless($shift, 404);

        $done = $shift->completedTransactions()->get();

        return view('kasir.shift-close', [
            'shift'    => $shift,
            'cash'     => (float) $done->where('payment_method', 'cash')->sum('total'),
            'qris'     => (float) $done->where('payment_method', 'qris')->sum('total'),
            'cashN'    => $done->where('payment_method', 'cash')->count(),
            'qrisN'    => $done->where('payment_method', 'qris')->count(),
        ]);
    }

    public function update(Request $request)
    {
        $shift = $request->user()->openShift();
        abort_unless($shift, 404);

        $data = $request->validate([
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'note'        => ['nullable', 'string', 'max:1000'],
        ]);

        // BR-08
        $expected   = $shift->expectedCash();
        $actual     = (float) $data['actual_cash'];
        $difference = $actual - $expected;

        $shift->update([
            'closed_at'     => now(),
            'expected_cash' => $expected,
            'actual_cash'   => $actual,
            'difference'    => $difference,
            'note'          => $data['note'] ?? null,
            'status'        => 'closed',
        ]);

        AuditLogger::log(
            'update',
            'Tutup shift '.$shift->shift_type.' · selisih '.rupiah($difference),
            $shift,
            null,
            $shift->only(['expected_cash', 'actual_cash', 'difference']),
        );

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Shift ditutup. Selisih kas '.rupiah($difference).'.');
    }
}
