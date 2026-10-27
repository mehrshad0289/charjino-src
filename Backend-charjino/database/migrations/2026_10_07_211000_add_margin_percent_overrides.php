<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('margin_percent', 8, 4)->nullable()->after('gift_proxy');
        });

        Schema::table('skus', function (Blueprint $table) {
            $table->decimal('margin_percent', 8, 4)->nullable()->after('price_manual');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('margin_percent');
        });
        Schema::table('skus', function (Blueprint $table) {
            $table->dropColumn('margin_percent');
        });
    }
};
