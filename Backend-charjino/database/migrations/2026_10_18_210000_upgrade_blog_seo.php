<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('meta_title', 120)->nullable()->after('excerpt');
            $table->string('meta_description', 200)->nullable()->after('meta_title');
            $table->string('canonical_url', 2048)->nullable()->after('meta_description');
            $table->boolean('noindex')->default(false)->after('canonical_url');
            $table->string('focus_keyword', 80)->nullable()->after('noindex');
            $table->string('cover_alt', 180)->nullable()->after('cover');
            $table->unsignedInteger('cover_width')->nullable()->after('cover_alt');
            $table->unsignedInteger('cover_height')->nullable()->after('cover_width');
            $table->json('faq')->nullable()->after('content');
            $table->unsignedSmallInteger('reading_minutes')->default(0)->after('comments_count');
        });

        Schema::table('blog_categories', function (Blueprint $table) {
            $table->longText('body_html')->nullable()->after('description');
            $table->string('meta_title', 120)->nullable()->after('body_html');
            $table->string('meta_description', 200)->nullable()->after('meta_title');
            $table->boolean('noindex')->default(false)->after('meta_description');
            $table->string('image_alt', 180)->nullable()->after('image');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('blog_slug', 80)->nullable()->unique()->after('name');
        });

        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('description', 500)->nullable();
            $table->longText('body_html')->nullable();
            $table->string('image')->nullable();
            $table->string('image_alt', 180)->nullable();
            $table->string('meta_title', 120)->nullable();
            $table->string('meta_description', 200)->nullable();
            $table->boolean('force_noindex')->default(false);
            $table->boolean('noindex')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('blog_post_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('blog_tags')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['post_id', 'tag_id']);
        });

        Schema::create('blog_post_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->timestamps();
            $table->unique(['post_id', 'product_id']);
            $table->index('product_id');
        });

        Schema::create('blog_post_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->string('user_id', 36)->nullable()->index();
            $table->json('payload');
            $table->timestamps();
        });

        Schema::create('blog_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16);
            $table->string('from_slug', 80);
            $table->string('to_slug', 80);
            $table->timestamps();
            $table->unique(['type', 'from_slug']);
            $table->index(['type', 'to_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_slug_redirects');
        Schema::dropIfExists('blog_post_revisions');
        Schema::dropIfExists('blog_post_product');
        Schema::dropIfExists('blog_post_tag');
        Schema::dropIfExists('blog_tags');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['blog_slug']);
            $table->dropColumn('blog_slug');
        });

        Schema::table('blog_categories', function (Blueprint $table) {
            $table->dropColumn(['body_html', 'meta_title', 'meta_description', 'noindex', 'image_alt']);
        });

        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn([
                'meta_title',
                'meta_description',
                'canonical_url',
                'noindex',
                'focus_keyword',
                'cover_alt',
                'cover_width',
                'cover_height',
                'faq',
                'reading_minutes',
            ]);
        });
    }
};
