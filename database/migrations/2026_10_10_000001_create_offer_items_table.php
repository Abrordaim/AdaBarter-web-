<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->enum('role', ['offerer', 'target'])->default('offerer');
            $table->timestamps();

            $table->unique(['offer_id', 'item_id', 'role']);
            $table->index(['offer_id', 'role']);
        });

        // Migrate existing offers if any
        if (Schema::hasTable('offers')) {
            $existingOffers = DB::table('offers')->get();
            foreach ($existingOffers as $offer) {
                if (!empty($offer->offerer_item_id)) {
                    DB::table('offer_items')->insertOrIgnore([
                        'offer_id'   => $offer->id,
                        'item_id'    => $offer->offerer_item_id,
                        'role'       => 'offerer',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_items');
    }
};
