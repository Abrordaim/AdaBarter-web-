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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reported_user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('reportable_type');
            $table->unsignedBigInteger('reportable_id');
            $table->string('reason'); // penipuan, barang_terlarang, deskripsi_palsu, pelecehan, lainnya
            $table->text('description');
            $table->string('evidence_image')->nullable();
            $table->enum('status', ['pending', 'resolved'])->default('pending');
            $table->string('action_taken')->nullable(); // item_moderated, item_deleted, user_banned, dismissed
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['reportable_type', 'reportable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
