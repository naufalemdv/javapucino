<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            // operational = mengurangi laba kotor · inventory = pembelian stok (tidak mengurangi)
            $table->enum('type', ['operational', 'inventory'])->default('operational');
            $table->boolean('is_locked')->default(false);   // kategori bawaan, tidak bisa dihapus
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();                          // BR-09
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};