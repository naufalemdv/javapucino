<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'sku', 'name', 'icon', 'image_path',
        'price', 'hpp', 'stock', 'is_best_seller', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'          => 'decimal:2',
            'hpp'            => 'decimal:2',
            'stock'          => 'integer',
            'is_best_seller' => 'boolean',
            'is_active'      => 'boolean',
        ];
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? asset('storage/'.$this->image_path) : null;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return $q->when($term, fn ($x) => $x->where(function ($w) use ($term) {
            $w->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%");
        }));
    }

    /** BR-13: status stok produk. */
    public function stockState(): string
    {
        if ($this->stock <= 0) {
            return 'gone';
        }

        return $this->stock <= (int) setting('low_stock_threshold', 5) ? 'low' : 'ok';
    }

    public function marginPercent(): int
    {
        if ((float) $this->price <= 0) {
            return 0;
        }

        return (int) round((((float) $this->price - (float) $this->hpp) / (float) $this->price) * 100);
    }
}
