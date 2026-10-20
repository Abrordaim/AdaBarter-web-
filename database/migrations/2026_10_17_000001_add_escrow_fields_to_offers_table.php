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
        Schema::table('offers', function (Blueprint $table) {
            // Metode pembayaran: cod (tunai/tatap muka) atau gateway (Midtrans escrow)
            $table->string('payment_method', 20)->default('cod')->after('cash_supplement_by');

            // Nominal selisih barter disimpan sebagai integer rupiah (unsignedBigInteger)
            $table->unsignedBigInteger('price_difference')->default(0)->after('payment_method');

            // Identifikasi pihak pembayar selisih (payer) dan penerima dana (receiver)
            $table->foreignId('payer_id')->nullable()->after('price_difference')->constrained('users')->nullOnDelete();
            $table->foreignId('receiver_id')->nullable()->after('payer_id')->constrained('users')->nullOnDelete();

            // Status escrow (WAITING_PAYMENT, HELD, WAITING_CONFIRMATION, COMPLETED, DISPUTED, REFUNDED, EXPIRED)
            $table->string('escrow_status', 30)->nullable()->after('receiver_id');

            // Waktu konfirmasi serah terima oleh masing-masing pihak
            $table->timestamp('payer_confirmed_at')->nullable()->after('escrow_status');
            $table->timestamp('receiver_confirmed_at')->nullable()->after('payer_confirmed_at');

            // Batas waktu konfirmasi otomatis (auto-release deadline) dan waktu pelepasan dana
            $table->timestamp('confirm_deadline_at')->nullable()->after('receiver_confirmed_at');
            $table->timestamp('released_at')->nullable()->after('confirm_deadline_at');

            // Kode serah terima 6 digit untuk validasi fisik saat tatap muka
            $table->string('handover_code', 6)->nullable()->after('released_at');
            $table->unsignedTinyInteger('handover_attempts')->default(0)->after('handover_code');

            // Index untuk mempercepat query pencarian dan cron job auto-release
            $table->index('payment_method');
            $table->index('escrow_status');
            $table->index('payer_id');
            $table->index('receiver_id');
            $table->index('confirm_deadline_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropForeign(['payer_id']);
            $table->dropForeign(['receiver_id']);
            $table->dropIndex(['payment_method']);
            $table->dropIndex(['escrow_status']);
            $table->dropIndex(['payer_id']);
            $table->dropIndex(['receiver_id']);
            $table->dropIndex(['confirm_deadline_at']);

            $table->dropColumn([
                'payment_method',
                'price_difference',
                'payer_id',
                'receiver_id',
                'escrow_status',
                'payer_confirmed_at',
                'receiver_confirmed_at',
                'confirm_deadline_at',
                'released_at',
                'handover_code',
                'handover_attempts',
            ]);
        });
    }
};
