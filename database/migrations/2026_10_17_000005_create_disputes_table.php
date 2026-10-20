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
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();

            // Relasi ke transaksi barter yang dipermasalahkan
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();

            // User yang mengajukan sanggahan/komplain
            $table->foreignId('reported_by')->constrained('users');

            // Kategori dan rincian masalah
            $table->string('reason', 100);
            $table->text('description');

            // Bukti foto/dokumen (array path gambar)
            $table->json('evidence_paths')->nullable();

            // Status penanganan dispute: open, resolved
            $table->string('status', 30)->default('open');

            // Bentuk keputusan admin: refund_full, refund_partial, release
            $table->string('resolution', 30)->nullable();

            // Nominal pengembalian dana (jika refund) dalam integer rupiah
            $table->unsignedBigInteger('refund_amount')->nullable();

            // Admin yang menangani dispute dan waktu penyelesaian
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            // Catatan pertimbangan admin
            $table->text('admin_note')->nullable();

            $table->timestamps();

            // Index
            $table->index('offer_id');
            $table->index('reported_by');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
