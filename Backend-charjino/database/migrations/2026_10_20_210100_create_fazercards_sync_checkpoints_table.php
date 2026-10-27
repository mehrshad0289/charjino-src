<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fazercards_sync_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('period', 32);
            $table->string('last_processed_id', 64)->nullable();
            $table->unsignedInteger('last_index')->default(0);
            $table->string('status', 16)->default('running');
            $table->timestamps();
            $table->unique(['name', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fazercards_sync_checkpoints');
    }
};
