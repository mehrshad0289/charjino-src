<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('handle')->unique();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('publisher');
            $table->string('category_name');
            $table->string('subtitle');
            $table->string('thumbnail');
            $table->boolean('is_popular')->default(false);
            $table->json('inputs')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::create('skus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedBigInteger('price');
            $table->string('cost_usd')->nullable();
            $table->string('region')->nullable();
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'code']);
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('src');
            $table->string('title');
            $table->string('href');
            $table->string('description');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('flash_sale_items', function (Blueprint $table) {
            $table->id();
            $table->string('image');
            $table->unsignedBigInteger('original_price');
            $table->unsignedBigInteger('price');
            $table->string('product_code');
            $table->string('product_handle');
            $table->string('product_name');
            $table->string('product_variant_code');
            $table->string('product_variant_name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_items');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('skus');
        Schema::dropIfExists('products');
    }
};
