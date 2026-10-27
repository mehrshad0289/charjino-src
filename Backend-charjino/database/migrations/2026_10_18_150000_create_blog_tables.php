<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('description', 500)->nullable();
            $table->string('image')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('content');
            $table->string('cover')->nullable();
            $table->foreignId('category_id')->constrained('blog_categories')->restrictOnDelete();
            $table->string('user_id', 36)->nullable()->index();
            $table->string('status', 16)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'published_at']);
        });

        Schema::create('blog_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->string('user_id', 36)->index();
            $table->foreignId('parent_id')->nullable()->constrained('blog_comments')->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 16)->default('approved')->index();
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('blog_reactions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 36);
            $table->string('reactable_type', 16);
            $table->unsignedBigInteger('reactable_id');
            $table->string('vote', 8)->default('like');
            $table->timestamps();

            $table->unique(['user_id', 'reactable_type', 'reactable_id']);
            $table->index(['reactable_type', 'reactable_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_reactions');
        Schema::dropIfExists('blog_comments');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');
    }
};
