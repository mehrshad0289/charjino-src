<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fazercards_audits', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 36)->nullable()->index();
            $table->string('action', 80);
            $table->string('method', 10);
            $table->string('path', 190);
            $table->boolean('ok')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fazercards_audits');
    }
};
