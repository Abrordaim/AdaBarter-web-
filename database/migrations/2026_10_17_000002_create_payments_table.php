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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Relasi ke transaksi barter (offers)
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();

            // Kode order unik untuk gateway (contoh: BRT-12-abcdef12)
            $table->string('order_id', 100)->unique();

            // Nominal kotor dalam integer rupiah murni
            $table->unsignedBigInteger('gross_amount');

            // Provider gateway (default midtrans)
            $table->string('gateway', 50)->default('midtrans');

            // ID transaksi resmi dari Midtrans (transaction_id)
            $table->string('gateway_transaction_id', 100)->nullable();

            // Metode pembayaran yang dipilih di Snap (qris, bank_transfer, gopay, dll)
            $table->string('payment_type', 50)->nullable();

            // Status pembayaran Midtrans: pending, settlement, expire, cancel, deny
            $table->string('status', 30)->default('pending');

            // Token Snap Midtrans untuk memunculkan popup pembayaran
            $table->string('snap_token', 255)->nullable();

            // Salinan raw JSON payload dari respon/webhook Midtrans untuk audit
            $table->json('raw_payload')->nullable();

            // Waktu keberhasilan pembayaran diterima
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            // Index pencarian
            $table->index('offer_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
