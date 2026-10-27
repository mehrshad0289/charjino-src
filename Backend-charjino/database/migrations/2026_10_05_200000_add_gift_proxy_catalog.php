<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('gift_proxy')->default(false);
        });

        Schema::table('skus', function (Blueprint $table) {
            $table->foreignId('gift_sku_id')->nullable()->constrained('skus')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gift_sku_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('gift_proxy');
        });
    }
};
