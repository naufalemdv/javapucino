<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'shift_type', 'opened_at', 'closed_at', 'opening_cash',
        'expected_cash', 'actual_cash', 'difference', 'note', 'status',
    ];

    protected function casts(): array
    {
        return [
            'opened_at'     => 'datetime',
            'closed_at'     => 'datetime',
            'opening_cash'  => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'actual_cash'   => 'decimal:2',
            'difference'    => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function completedTransactions(): HasMany
    {
        return $this->transactions()->where('status', 'completed');
    }

    /** BR-08: modal awal + seluruh penjualan tunai completed. */
    public function expectedCash(): float
    {
        $cash = (float) $this->completedTransactions()->where('payment_method', 'cash')->sum('total');

        return (float) $this->opening_cash + $cash;
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
