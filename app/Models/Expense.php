<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'expense_category_id', 'user_id', 'related_user_id', 'stock_movement_id',
        'expense_date', 'title', 'amount', 'payment_method', 'note',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount'       => 'decimal:2',
        ];
    }

    /* ---------- Relasi ---------- */

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function relatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    /* ---------- Scope ---------- */

    public function scopeInMonth(Builder $q, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $q->whereBetween('expense_date', [
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        ]);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return $q->when($term, fn ($x) => $x->where(function ($w) use ($term) {
            $w->where('title', 'like', "%{$term}%")
              ->orWhere('note', 'like', "%{$term}%");
        }));
    }

    /* ---------- Helper ---------- */

    /** Hanya biaya operasional yang mengurangi laba kotor (BR-16). */
    public function isOperational(): bool
    {
        return $this->category?->type !== 'inventory';
    }

    /** BR-20: baris hasil restock tidak boleh diubah/dihapus dari menu Pengeluaran. */
    public function isLocked(): bool
    {
        return $this->stock_movement_id !== null;
    }

    public function paymentLabel(): string
    {
        return match ($this->payment_method) {
            'transfer' => 'Transfer',
            'qris'     => 'QRIS',
            default    => 'Tunai',
        };
    }

    /** Dibuat otomatis dari restock bahan — tidak pernah diinput manual. */
    public static function createFromRestock(StockMovement $movement, Material $material, float $unitCost): self
    {
        $qty = (float) $movement->qty;

        return static::create([
            'expense_category_id' => ExpenseCategory::defaultInventory()->id,
            'user_id'             => auth()->id(),
            'stock_movement_id'   => $movement->id,
            'expense_date'        => now()->toDateString(),
            'title'               => 'Pembelian '.$material->name.' '.angka($qty, 2).' '.$material->unit,
            'amount'              => round($qty * $unitCost, 2),
            'payment_method'      => 'cash',
            'note'                => 'Dicatat otomatis dari restock bahan.',
        ]);
    }
}