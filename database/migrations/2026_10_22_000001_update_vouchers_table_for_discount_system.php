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
        Schema::table('vouchers', function (Blueprint $table) {
            $table->unsignedInteger('quota_amount')->default(0)->nullable()->change();
            $table->string('type', 20)->default('fixed')->after('description'); // 'fixed' | 'percentage'
            $table->unsignedBigInteger('discount_amount')->nullable()->after('type'); // in Rupiah
            $table->unsignedTinyInteger('percentage')->nullable()->after('discount_amount'); // 1 - 100
            $table->unsignedBigInteger('max_discount_amount')->nullable()->after('percentage'); // cap for percentage
            $table->unsignedBigInteger('min_transaction_amount')->default(0)->after('max_discount_amount'); // minimum price difference
            $table->string('distribution', 20)->default('public')->after('min_transaction_amount'); // 'public' | 'private'
        });

        Schema::table('voucher_claims', function (Blueprint $table) {
            $table->boolean('is_used')->default(false)->after('claimed_at');
            $table->timestamp('used_at')->nullable()->after('is_used');
            $table->foreignId('offer_id')->nullable()->after('used_at')->constrained('offers')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('voucher_claim_id')->nullable()->after('offer_id')->constrained('voucher_claims')->nullOnDelete();
            $table->unsignedBigInteger('original_amount')->nullable()->after('order_id');
            $table->unsignedBigInteger('discount_amount')->default(0)->after('original_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['voucher_claim_id']);
            $table->dropColumn(['voucher_claim_id', 'original_amount', 'discount_amount']);
        });

        Schema::table('voucher_claims', function (Blueprint $table) {
            $table->dropForeign(['offer_id']);
            $table->dropColumn(['is_used', 'used_at', 'offer_id']);
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'discount_amount',
                'percentage',
                'max_discount_amount',
                'min_transaction_amount',
                'distribution',
            ]);
        });
    }
};
