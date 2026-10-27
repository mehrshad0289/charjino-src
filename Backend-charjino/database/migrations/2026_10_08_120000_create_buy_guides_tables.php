<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buy_guides', function (Blueprint $table) {
            $table->id();
            $table->string('scope_key', 160)->unique();
            $table->string('scope_type', 20);
            $table->string('category', 40);
            $table->string('subcategory', 80)->default('');
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->longText('intro')->nullable();
            $table->string('status', 20)->default('draft');
            $table->date('last_reviewed_at')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('updated_by', 36)->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'category', 'subcategory']);
            $table->index(['status', 'category']);
        });

        Schema::create('buy_guide_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buy_guide_id')->constrained('buy_guides')->cascadeOnDelete();
            $table->string('type', 40);
            $table->boolean('inherit')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('data')->nullable();
            $table->timestamps();

            $table->unique(['buy_guide_id', 'type']);
        });

        Schema::create('buy_guide_media', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 32)->default('public');
            $table->string('path', 500);
            $table->string('original_path', 500)->nullable();
            $table->string('mime', 80)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->string('alt', 180);
            $table->json('variants')->nullable();
            $table->string('kind', 16)->default('image');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buy_guide_sections');
        Schema::dropIfExists('buy_guides');
        Schema::dropIfExists('buy_guide_media');
    }
};
