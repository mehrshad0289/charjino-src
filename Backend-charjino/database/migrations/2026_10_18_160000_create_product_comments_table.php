<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('comments_count')->default(0);
        });

        Schema::create('product_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('user_id', 36)->index();
            $table->foreignId('parent_id')->nullable()->constrained('product_comments')->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 16)->default('approved')->index();
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_comments');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('comments_count');
        });
    }
};
