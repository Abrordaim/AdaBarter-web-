<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tambahkan keuntungan bonus kuota slot dan bonus slot boost pada vip_plans
        Schema::table('vip_plans', function (Blueprint $table) {
            $table->unsignedInteger('bonus_post_quota')->default(0)->after('price');
            $table->unsignedInteger('bonus_boost_quota')->default(0)->after('bonus_post_quota');
        });

        // 2. Tambahkan boost_quota dan vip_expires_at pada users
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('vip_expires_at')->nullable()->after('is_vip');
            $table->unsignedInteger('boost_quota')->default(0)->after('bonus_post_quota');
        });

        // 3. Perluas tabel payments agar mendukung pembayaran VIP dan Boost
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->string('type', 30)->default('escrow')->after('user_id');
            $table->unsignedBigInteger('reference_id')->nullable()->after('type');
            $table->json('metadata')->nullable()->after('raw_payload');
        });

        // Buat offer_id nullable pada payments jika MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE payments MODIFY offer_id BIGINT UNSIGNED NULL;');
        }

        // 4. Hapus tabel slot_packages (fitur beli slot dihapus)
        Schema::dropIfExists('slot_packages');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-create slot_packages jika rollback
        if (!Schema::hasTable('slot_packages')) {
            Schema::create('slot_packages', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->unsignedInteger('slots');
                $table->decimal('price', 12, 2);
                $table->string('badge')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'type', 'reference_id', 'metadata']);
        });

        DB::statement('ALTER TABLE payments MODIFY offer_id BIGINT UNSIGNED NOT NULL;');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['vip_expires_at', 'boost_quota']);
        });

        Schema::table('vip_plans', function (Blueprint $table) {
            $table->dropColumn(['bonus_post_quota', 'bonus_boost_quota']);
        });
    }
};
