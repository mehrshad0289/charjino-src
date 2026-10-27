<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('search_tags', function (Blueprint $table) {
            $table->string('kind', 32)->default('tag')->after('attach_pattern');
            $table->foreignId('catalog_group_id')->nullable()->after('kind')->constrained('catalog_groups')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('catalog_group_id');
            $table->integer('sort_order')->default(0)->after('is_active');
            $table->index('kind');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('search_tags', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalog_group_id');
            $table->dropIndex(['kind']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['kind', 'is_active', 'sort_order']);
        });
    }
};
