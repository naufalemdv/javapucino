<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * BR-11: append-only. Tidak disediakan aksi ubah/hapus di aplikasi.
 */
class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'description', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return $q->when($term, fn ($x) => $x->where(function ($w) use ($term) {
            $w->where('description', 'like', "%{$term}%")
              ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%"));
        }));
    }

    public function tagClass(): string
    {
        return [
            'login'   => 't-gray',
            'logout'  => 't-gray',
            'create'  => 't-green',
            'update'  => 't-amber',
            'delete'  => 't-red',
            'void'    => 't-red',
            'print'   => 't-gray',
            'restock' => 't-green',
        ][$this->action] ?? 't-gray';
    }
}
