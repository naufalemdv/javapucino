<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-07: setiap transaksi wajib terhubung ke shift aktif.
 * Kasir tanpa shift terbuka diarahkan ke halaman buka shift.
 */
class EnsureShiftIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        $shift = $request->user()?->openShift();

        if (! $shift) {
            return redirect()->route('kasir.shift.create')
                ->with('info', 'Buka shift terlebih dahulu sebelum masuk ke kasir.');
        }

        $request->attributes->set('shift', $shift);
        app()->instance('current.shift', $shift);

        return $next($request);
    }
}
