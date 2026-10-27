<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('number', 24)->unique();
            $table->string('user_id', 36)->index();
            $table->string('subject');
            $table->string('category', 32)->default('other');
            $table->string('order_number', 32)->nullable();
            $table->string('status', 16)->default('open')->index();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id', 36)->index();
            $table->string('sender', 16);
            $table->text('body');
            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
        });

        Schema::create('user_favorites', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 36);
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_favorites');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
    }
};
