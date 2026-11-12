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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('penalty_points')->default(0)->after('is_banned');
            $table->string('account_status')->default('active')->after('penalty_points');
            $table->unsignedTinyInteger('restriction_tier')->default(0)->after('account_status');
            $table->timestamp('restricted_until')->nullable()->after('restriction_tier');
            $table->json('active_restrictions')->nullable()->after('restricted_until');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->integer('penalty_points_awarded')->nullable()->after('action_taken');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['penalty_points_awarded']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'penalty_points',
                'account_status',
                'restriction_tier',
                'restricted_until',
                'active_restrictions',
            ]);
        });
    }
};
