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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            // Relasi ke dompet user
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();

            // Tipe mutasi: credit (saldo masuk) atau debit (saldo keluar)
            $table->enum('type', ['credit', 'debit']);

            // Jumlah dana mutasi (selalu positif dalam integer rupiah)
            $table->unsignedBigInteger('amount');

            // Alasan mutasi: escrow_release, refund, withdrawal, withdrawal_rejected
            $table->string('reason', 50);

            // Referensi polimorfik ke sumber transaksi (misal: Offer, Dispute, Withdrawal)
            $table->nullableMorphs('reference');

            // Saldo dompet setelah transaksi ini tercatat (snapshot audit)
            $table->unsignedBigInteger('balance_after');

            // Waktu pencatatan ledger (append-only)
            $table->timestamps();

            // Index pencarian riwayat transaksi dompet
            $table->index('wallet_id');
            $table->index('type');
            $table->index('reason');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
