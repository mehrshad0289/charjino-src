<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_groups', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('title');
            $table->index('name_en');
            $table->index('title');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('name_en');
            $table->index('title');
        });

        Schema::table('skus', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
            $table->index('name_en');
        });

        DB::table('products')->where(function ($query) {
            $query->whereNull('name_en')->orWhere('name_en', '');
        })->update(['name_en' => DB::raw('title')]);

        $groupEn = [
            'game-topup' => 'Game top-up',
            'gift-card' => 'Gift cards',
            'game-keys' => 'Game keys',
            'steam-wallet' => 'Steam wallet',
            'steam-gifts' => 'Steam gifts',
            'telegram' => 'Telegram',
            'manual-services' => 'Manual services',
        ];
        foreach ($groupEn as $slug => $english) {
            DB::table('catalog_groups')->where('slug', $slug)->where(function ($query) {
                $query->whereNull('name_en')->orWhere('name_en', '');
            })->update(['name_en' => $english]);
        }

        DB::table('skus')->where(function ($query) {
            $query->whereNull('name_en')->orWhere('name_en', '');
        })->update(['name_en' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->dropIndex(['name_en']);
            $table->dropColumn('name_en');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['name_en']);
            $table->dropIndex(['title']);
        });
        Schema::table('catalog_groups', function (Blueprint $table) {
            $table->dropIndex(['name_en']);
            $table->dropIndex(['title']);
            $table->dropColumn('name_en');
        });
    }
};
