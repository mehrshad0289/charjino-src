<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('catalog_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop2_big_category_id')->nullable()->unique();
            $table->string('name');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('catalog_group_id')->nullable()->after('id')->constrained('catalog_groups')->nullOnDelete();
            $table->unsignedBigInteger('shop2_category_id')->nullable()->unique()->after('catalog_group_id');
            $table->unsignedBigInteger('shop2_big_category_id')->nullable()->after('shop2_category_id');
            $table->string('name_en')->nullable()->after('title');
            $table->text('description')->nullable()->after('subtitle');
            $table->string('image_url')->nullable()->after('thumbnail');
            $table->boolean('visible')->default(true)->after('is_popular');
            $table->string('fulfillment_type', 32)->nullable()->after('visible');
            $table->json('requirements')->nullable()->after('inputs');
        });

        Schema::table('skus', function (Blueprint $table) {
            $table->unsignedBigInteger('shop2_item_id')->nullable()->unique()->after('product_id');
            $table->string('fulfillment_type', 32)->nullable()->after('name');
            $table->string('live_cost_usd')->nullable()->after('cost_usd');
            $table->boolean('visible')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->dropColumn(['shop2_item_id', 'fulfillment_type', 'live_cost_usd', 'visible']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalog_group_id');
            $table->dropColumn([
                'shop2_category_id',
                'shop2_big_category_id',
                'name_en',
                'description',
                'image_url',
                'visible',
                'fulfillment_type',
                'requirements',
            ]);
        });

        Schema::dropIfExists('catalog_groups');
        Schema::dropIfExists('settings');
    }
};
