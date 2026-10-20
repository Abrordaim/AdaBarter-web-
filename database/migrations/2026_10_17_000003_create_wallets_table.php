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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();

            // Satu user hanya memiliki satu dompet digital utama
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            // Saldo dompet dalam integer rupiah (unsignedBigInteger, tidak boleh negatif)
            $table->unsignedBigInteger('balance')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
