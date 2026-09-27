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
        $action = $request->string('action')->toString() ?: 'all';

        $logs = AuditLog::with('user')
            ->search($request->string('q')->toString())
            ->when($action !== 'all', fn ($q) => $q->where('action', $action))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit', [
            'logs'    => $logs,
            'action'  => $action,
            'q'       => $request->string('q')->toString(),
            'actions' => ['all', 'login', 'create', 'update', 'delete', 'void', 'print', 'restock'],
            'total'   => AuditLog::count(),
        ]);
    }
}
