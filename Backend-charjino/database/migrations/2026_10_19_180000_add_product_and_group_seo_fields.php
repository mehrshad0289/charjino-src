<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('seo_title', 180)->nullable()->after('subtitle');
            $table->string('seo_description', 300)->nullable()->after('seo_title');
        });

        Schema::table('catalog_groups', function (Blueprint $table) {
            $table->string('seo_title', 180)->nullable()->after('description');
            $table->string('seo_description', 300)->nullable()->after('seo_title');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'seo_description']);
        });
        Schema::table('catalog_groups', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'seo_description']);
        });
    }
};
