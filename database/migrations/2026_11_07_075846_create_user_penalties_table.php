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
        Schema::create('user_penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('report_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('points')->default(1);
            $table->string('category')->default('lainnya');
            $table->text('reason');
            $table->string('status')->default('active'); // active, appealed, revoked, expired
            $table->timestamp('expires_at')->nullable();
            $table->string('appeal_status')->default('none'); // none, pending, accepted, rejected
            $table->text('appeal_reason')->nullable();
            $table->string('appeal_evidence')->nullable();
            $table->timestamp('appealed_at')->nullable();
            $table->foreignId('appeal_resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('appeal_resolved_at')->nullable();
            $table->text('appeal_admin_notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_penalties');
    }
};
