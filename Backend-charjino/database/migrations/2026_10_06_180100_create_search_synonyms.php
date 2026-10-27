<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_synonyms', function (Blueprint $table) {
            $table->id();
            $table->string('term', 120);
            $table->json('synonyms');
            $table->string('group_slug', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('term');
            $table->index('group_slug');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_synonyms');
    }
};
