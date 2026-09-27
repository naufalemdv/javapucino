<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name', 120);    // BR-04 snapshot
            $table->string('icon', 20)->nullable();
            $table->decimal('price', 12, 2);        // snapshot harga jual
            $table->decimal('hpp', 12, 2);          // snapshot modal
            $table->integer('qty');
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();

            $table->index(['product_id', 'transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};
