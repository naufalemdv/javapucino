<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'type', 'is_locked', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_locked'  => 'boolean',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('name');
    }

    public function scopeOperational(Builder $q): Builder
    {
        return $q->where('type', 'operational');
    }

    public function isOperational(): bool
    {
        return $this->type === 'operational';
    }

    public function typeLabel(): string
    {
        return $this->isOperational() ? 'Operasional' : 'Pembelian Stok';
    }

    /** Kategori tujuan otomatis untuk restock bahan (BR-20). */
    public static function defaultInventory(): self
    {
        return static::firstOrCreate(
            ['type' => 'inventory', 'is_locked' => true],
            ['name' => 'Pembelian Bahan', 'is_active' => true, 'sort_order' => 90],
        );
    }
}