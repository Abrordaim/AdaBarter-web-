<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel Provinsi (34 provinsi Indonesia)
        Schema::create('provinces', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('code', 2)->unique();
            $table->string('name', 100);
            $table->index('name');
        });

        // Tabel Kabupaten / Kota (514 kab/kota)
        Schema::create('regencies', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('code', 4)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('province_id');
            $table->foreign('province_id')->references('id')->on('provinces')->onDelete('cascade');
            $table->index('province_id');
            $table->index('name');
        });

        // Tabel Kecamatan (7.200+ kecamatan)
        Schema::create('districts', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('code', 7)->unique();
            $table->string('name', 100);
            $table->unsignedSmallInteger('regency_id');
            $table->foreign('regency_id')->references('id')->on('regencies')->onDelete('cascade');
            $table->index('regency_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
        Schema::dropIfExists('regencies');
        Schema::dropIfExists('provinces');
    }
};
