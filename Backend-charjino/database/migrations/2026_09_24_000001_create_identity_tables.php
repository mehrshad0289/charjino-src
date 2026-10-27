<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 11)->unique();
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 36)->index();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
        Schema::dropIfExists('otp_challenges');
    }
};
