<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('media_type', 16)->default('image')->after('src');
            $table->boolean('visible')->default(true)->after('sort_order');
        });

        Schema::table('flash_sale_items', function (Blueprint $table) {
            $table->foreignId('sku_id')->nullable()->after('id')->constrained('skus')->nullOnDelete();
            $table->boolean('visible')->default(true)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('flash_sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sku_id');
            $table->dropColumn('visible');
        });

        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'visible']);
        });
    }
};
