<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'material_id', 'user_id', 'type', 'qty',
        'stock_before', 'stock_after', 'reference_type', 'reference_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'qty'          => 'decimal:2',
            'stock_before' => 'decimal:2',
            'stock_after'  => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function typeLabel(): string
    {
        return [
            'sale'        => 'Penjualan',
            'void_return' => 'Void',
            'restock'     => 'Restock',
            'adjustment'  => 'Penyesuaian',
        ][$this->type] ?? $this->type;
    }

    public function itemName(): string
    {
        return $this->product?->name ?? $this->material?->name ?? '—';
    }
}
