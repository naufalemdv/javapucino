<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;

/**
 * BR-12: setiap perubahan stok wajib mencatat stok sebelum & sesudah.
 */
class StockService
{
    public static function moveProduct(
        Product $product,
        float $qty,
        string $type,
        ?Model $reference = null,
        ?string $note = null,
    ): StockMovement {
        $before = (float) $product->stock;
        $after  = max(0, $before + $qty);          // BR-05: stok tidak boleh negatif

        $product->forceFill(['stock' => $after])->save();

        return StockMovement::create([
            'product_id'     => $product->id,
            'user_id'        => auth()->id(),
            'type'           => $type,
            'qty'            => $qty,
            'stock_before'   => $before,
            'stock_after'    => $after,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id'   => $reference?->getKey(),
            'note'           => $note,
        ]);
    }

    public static function moveMaterial(
        Material $material,
        float $qty,
        string $type,
        ?string $note = null,
    ): StockMovement {
        $before = (float) $material->stock;
        $after  = max(0, $before + $qty);

        $material->forceFill(['stock' => $after])->save();

        return StockMovement::create([
            'material_id'  => $material->id,
            'user_id'      => auth()->id(),
            'type'         => $type,
            'qty'          => $qty,
            'stock_before' => $before,
            'stock_after'  => $after,
            'note'         => $note,
        ]);
    }
}
