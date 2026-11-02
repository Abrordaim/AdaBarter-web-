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
        Schema::create('meeting_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->foreignId('proposed_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('place_name');
            $table->text('address')->nullable();
            $table->dateTime('meet_at');
            $table->enum('status', ['proposed', 'agreed', 'locked', 'superseded'])->default('proposed');
            $table->foreignId('agreed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('agreed_by_other_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['offer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_points');
    }
};
