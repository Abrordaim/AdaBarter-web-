<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offerer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('offerer_item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('cash_supplement', 12, 2)->nullable();
            $table->enum('cash_supplement_by', ['offerer', 'target_owner'])->nullable();
            $table->enum('status', ['pending', 'matched', 'rejected', 'completed', 'cancelled'])->default('pending');
            $table->boolean('offerer_approved')->default(false);
            $table->boolean('target_approved')->default(false);
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};