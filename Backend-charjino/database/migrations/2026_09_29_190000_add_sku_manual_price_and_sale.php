<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->boolean('price_manual')->default(false)->after('price');
            $table->unsignedBigInteger('sale_price')->nullable()->after('price_manual');
            $table->timestamp('sale_ends_at')->nullable()->after('sale_price');
        });
    }

    public function down(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->dropColumn(['price_manual', 'sale_price', 'sale_ends_at']);
        });
    }
};
