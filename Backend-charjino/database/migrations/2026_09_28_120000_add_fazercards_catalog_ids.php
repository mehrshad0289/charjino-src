<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('fazercards_category_id', 120)->nullable()->unique()->after('shop2_big_category_id');
        });

        Schema::table('skus', function (Blueprint $table) {
            $table->string('fazercards_category_id', 120)->nullable()->after('shop2_item_id');
            $table->string('fazercards_offer_id', 120)->nullable()->after('fazercards_category_id');
            $table->unique(['fazercards_category_id', 'fazercards_offer_id'], 'skus_fc_category_offer_unique');
        });
    }

    public function down(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->dropUnique('skus_fc_category_offer_unique');
            $table->dropColumn(['fazercards_category_id', 'fazercards_offer_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('fazercards_category_id');
        });
    }
};
