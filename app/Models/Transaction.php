<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_no', 'shift_id', 'user_id', 'customer_name', 'queue_no', 'queue_status',
        'subtotal', 'tax_percent', 'tax_amount', 'total', 'payment_method',
        'paid_amount', 'change_amount', 'qris_reference', 'status',
        'voided_by', 'voided_at', 'void_reason', 'printed_count',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'      => 'decimal:2',
            'tax_percent'   => 'decimal:2',
            'tax_amount'    => 'decimal:2',
            'total'         => 'decimal:2',
            'paid_amount'   => 'decimal:2',
            'change_amount' => 'decimal:2',
            'voided_at'     => 'datetime',
            'queue_no'      => 'integer',
            'printed_count' => 'integer',
        ];
    }

    /* ---------- Relasi ---------- */

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    /* ---------- Scope ---------- */

    public function scopeCompleted(Builder $q): Builder
    {
        return $q->where('status', 'completed');
    }

    public function scopeToday(Builder $q): Builder
    {
        return $q->whereDate('created_at', now()->toDateString());
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return $q->when($term, fn ($x) => $x->where(function ($w) use ($term) {
            $w->where('invoice_no', 'like', "%{$term}%")
              ->orWhere('customer_name', 'like', "%{$term}%");
        }));
    }

    /* ---------- Helper ---------- */

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    public function paymentLabel(): string
    {
        return $this->payment_method === 'cash' ? 'Tunai' : 'QRIS';
    }

    public function queueLabel(): string
    {
        return str_pad((string) ($this->queue_no ?? 0), 3, '0', STR_PAD_LEFT);
    }

    public function totalHpp(): float
    {
        return (float) $this->items->sum(fn ($i) => (float) $i->hpp * $i->qty);
    }

    public function totalQty(): int
    {
        return (int) $this->items->sum('qty');
    }

    /** BR-01: TRX-YYYYMMDD-NNNN, urutan direset tiap hari. */
    public static function nextInvoiceNo(): string
    {
        $prefix = config('javapucino.invoice_prefix', 'TRX').'-'.now()->format('Ymd').'-';

        $last = static::where('invoice_no', 'like', $prefix.'%')
            ->orderByDesc('invoice_no')
            ->lockForUpdate()
            ->value('invoice_no');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public static function nextQueueNo(): int
    {
        return (int) static::whereDate('created_at', now()->toDateString())->max('queue_no') + 1;
    }
}
