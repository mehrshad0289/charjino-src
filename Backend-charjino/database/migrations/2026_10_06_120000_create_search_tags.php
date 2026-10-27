<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('label', 80);
            $table->json('aliases')->nullable();
            $table->string('aliases_text', 500)->default('');
            $table->string('attach_pattern', 160)->nullable();
            $table->timestamps();
            $table->index('label');
        });

        Schema::create('product_search_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('search_tag_id')->constrained('search_tags')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'search_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_search_tag');
        Schema::dropIfExists('search_tags');
    }
};
