<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 30)->unique();          // BR-01 TRX-YYYYMMDD-NNNN
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();   // kasir
            $table->string('customer_name', 100)->nullable();
            $table->unsignedInteger('queue_no')->nullable();      // nomor antrian harian
            $table->enum('queue_status', ['waiting', 'called', 'done'])->default('waiting');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('payment_method', ['cash', 'qris']);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('change_amount', 12, 2)->default(0);
            $table->string('qris_reference', 100)->nullable();
            $table->enum('status', ['completed', 'void'])->default('completed');
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->smallInteger('printed_count')->default(0);
            $table->timestamps();

            $table->index('created_at');
            $table->index(['status', 'created_at']);
            $table->index('payment_method');
            $table->index(['user_id', 'shift_id']);
            $table->index(['queue_status', 'queue_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
