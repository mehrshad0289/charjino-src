<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->change();
            $table->string('og_title', 120)->nullable()->after('focus_keyword');
            $table->string('og_description', 200)->nullable()->after('og_title');
            $table->string('og_image', 2048)->nullable()->after('og_description');
            $table->string('twitter_title', 120)->nullable()->after('og_image');
            $table->string('twitter_description', 200)->nullable()->after('twitter_title');
            $table->string('twitter_image', 2048)->nullable()->after('twitter_description');
            $table->json('secondary_keywords')->nullable()->after('twitter_image');
            $table->string('preview_token', 64)->nullable()->unique()->after('faq');
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('blog_categories')->restrictOnDelete();
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['status', 'updated_at']);
            $table->dropUnique(['preview_token']);
            $table->dropColumn([
                'og_title',
                'og_description',
                'og_image',
                'twitter_title',
                'twitter_description',
                'twitter_image',
                'secondary_keywords',
                'preview_token',
            ]);
            $table->unsignedBigInteger('category_id')->nullable(false)->change();
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('blog_categories')->restrictOnDelete();
        });
    }
};
