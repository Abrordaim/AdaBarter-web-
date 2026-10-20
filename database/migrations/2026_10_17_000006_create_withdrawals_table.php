<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();

            // Pengguna yang mengajukan penarikan dana
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Nominal penarikan dalam integer rupiah (unsignedBigInteger)
            $table->unsignedBigInteger('amount');

            // Data rekening bank tujuan
            $table->string('bank_name', 50);
            $table->string('account_number', 50);
            $table->string('account_name', 100);

            // Status penarikan: pending, approved, rejected, paid
            $table->string('status', 30)->default('pending');

            // Admin yang memproses penarikan
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();

            // Catatan atau alasan penolakan (jika rejected)
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            // Index
            $table->index('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
