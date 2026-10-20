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
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();

            // Nama gateway pembayaran (default: midtrans)
            $table->string('gateway', 50)->default('midtrans');

            // Seluruh isi payload JSON notifikasi webhook dari gateway
            $table->json('payload');

            // Apakah signature_key cocok dan tervalidasi
            $table->boolean('signature_valid')->default(false);

            // Waktu webhook selesai ditangani oleh sistem
            $table->timestamp('handled_at')->nullable();

            $table->timestamps();

            // Index
            $table->index('handled_at');
            $table->index('gateway');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
