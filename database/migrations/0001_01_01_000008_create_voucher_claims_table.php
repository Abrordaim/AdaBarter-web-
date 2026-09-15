<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->timestamp('claimed_at');
            $table->timestamps();
            
            $table->unique(['user_id', 'voucher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_claims');
    }
};