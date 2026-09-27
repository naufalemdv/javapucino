<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'unit', 'stock', 'min_stock'];

    protected function casts(): array
    {
        return [
            'stock'     => 'decimal:2',
            'min_stock' => 'decimal:2',
        ];
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** BR-13: bahan "Menipis" bila stock < min_stock. */
    public function isLow(): bool
    {
        return (float) $this->stock < (float) $this->min_stock;
    }
}
