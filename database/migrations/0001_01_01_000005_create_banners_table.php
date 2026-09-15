<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('image_url');
            $table->string('redirect_url')->nullable();
            $table->string('advertiser_name')->nullable();
            $table->enum('position', ['home_top', 'home_bottom', 'detail_page'])->default('home_top');
            $table->boolean('is_active')->default(true);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};