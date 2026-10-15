<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Kolom FK wilayah (nullable — backward compat dengan user lama)
            $table->unsignedSmallInteger('province_id')->nullable()->after('city');
            $table->unsignedSmallInteger('regency_id')->nullable()->after('province_id');
            $table->unsignedInteger('district_id')->nullable()->after('regency_id');

            // Nama denormalized — untuk display cepat tanpa JOIN
            $table->string('province_name', 100)->nullable()->after('district_id');
            $table->string('regency_name', 100)->nullable()->after('province_name');
            $table->string('district_name', 100)->nullable()->after('regency_name');

            // FK constraints (nullable: set null on delete)
            $table->foreign('province_id')->references('id')->on('provinces')->nullOnDelete();
            $table->foreign('regency_id')->references('id')->on('regencies')->nullOnDelete();
            $table->foreign('district_id')->references('id')->on('districts')->nullOnDelete();

            $table->index('regency_id');
            $table->index('province_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
            $table->dropForeign(['regency_id']);
            $table->dropForeign(['district_id']);
            $table->dropIndex(['regency_id']);
            $table->dropIndex(['province_id']);
            $table->dropColumn(['province_id', 'regency_id', 'district_id', 'province_name', 'regency_name', 'district_name']);
        });
    }
};
