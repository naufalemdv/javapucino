<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('related_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Terisi hanya untuk pengeluaran hasil restock bahan (BR-20)
            $table->foreignId('stock_movement_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('expense_date');
            $table->string('title', 120);
            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', ['cash', 'transfer', 'qris'])->default('cash');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('stock_movement_id');            // 1 restock = 1 pengeluaran
            $table->index('expense_date');
            $table->index(['expense_category_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};