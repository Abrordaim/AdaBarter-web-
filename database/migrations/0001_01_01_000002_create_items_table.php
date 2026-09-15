<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->enum('condition', ['baru', 'bekas_seperti_baru', 'bekas_baik', 'bekas_layak_pakai']);
            $table->text('desired_items')->nullable();
            $table->decimal('estimated_price', 12, 2)->nullable();
            $table->string('location')->nullable();
            $table->string('city')->nullable();
            $table->enum('status', ['active', 'inactive', 'moderated', 'traded'])->default('active');
            $table->boolean('is_boosted')->default(false);
            $table->timestamp('boost_expires_at')->nullable();
            $table->json('images')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};