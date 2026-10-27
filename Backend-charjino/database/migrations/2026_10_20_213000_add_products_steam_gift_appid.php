<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('steam_gift_appid')->nullable()->after('fazercards_category_id');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->index(['catalog_group_id', 'status', 'visible', 'id'], 'products_storefront_group_idx');
            $table->index(['catalog_group_id', 'steam_gift_appid', 'id'], 'products_steam_gift_appid_idx');
        });

        DB::table('products')
            ->select(['id', 'handle'])
            ->where('handle', 'like', 'steam-gift-%')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $appId = (int) substr((string) $row->handle, strlen('steam-gift-'));
                    if ($appId <= 0) {
                        continue;
                    }
                    DB::table('products')->where('id', $row->id)->update(['steam_gift_appid' => $appId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_storefront_group_idx');
            $table->dropIndex('products_steam_gift_appid_idx');
            $table->dropColumn('steam_gift_appid');
        });
    }
};
