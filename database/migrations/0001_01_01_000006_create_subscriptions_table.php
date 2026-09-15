<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('plan', ['vip', 'gold']);
            $table->decimal('price_paid', 10, 2)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('expired_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};