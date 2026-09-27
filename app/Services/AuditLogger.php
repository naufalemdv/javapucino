<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Catat aktivitas ke audit log (append-only, BR-11).
     */
    public static function log(
        string $action,
        string $description,
        ?Model $auditable = null,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id'        => $userId ?? auth()->id(),
            'action'         => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id'   => $auditable?->getKey(),
            'old_values'     => $old,
            'new_values'     => $new,
            'description'    => mb_substr($description, 0, 255),
            'ip_address'     => request()->ip(),
            'user_agent'     => mb_substr((string) request()->userAgent(), 0, 255),
        ]);
    }
}
