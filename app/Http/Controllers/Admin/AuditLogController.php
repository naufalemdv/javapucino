<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /** FR-25: read-only (BR-11). */
    public function index(Request $request)
    {
        $dates = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ], [], ['from' => 'tanggal awal', 'to' => 'tanggal akhir']);

        $from = $dates['from'] ?? null;
        $to = $dates['to'] ?? null;

        // Kalau tanggal terbalik, tukar supaya tetap ada hasil.
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $action = $request->string('action')->toString() ?: 'all';

        $logs = AuditLog::with('user')
            ->search($request->string('q')->toString())
            ->when($action !== 'all', fn ($q) => $q->where('action', $action))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit', [
            'logs' => $logs,
            'action' => $action,
            'q' => $request->string('q')->toString(),
            'from' => $from,
            'to' => $to,
            'actions' => ['all', 'login', 'create', 'update', 'delete', 'void', 'print', 'restock'],
            'total' => AuditLog::count(),
        ]);
    }
}