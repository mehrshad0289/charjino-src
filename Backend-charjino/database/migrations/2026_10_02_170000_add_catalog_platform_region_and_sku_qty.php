<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('platform', 80)->nullable()->after('fulfillment_type');
            $table->string('region', 80)->nullable()->after('platform');
            $table->index('platform');
            $table->index('region');
        });

        Schema::table('skus', function (Blueprint $table) {
            $table->unsignedInteger('min_order_quantity')->default(1)->after('region');
            $table->unsignedInteger('max_order_quantity')->default(100)->after('min_order_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['platform']);
            $table->dropIndex(['region']);
            $table->dropColumn(['platform', 'region']);
        });

        Schema::table('skus', function (Blueprint $table) {
            $table->dropColumn(['min_order_quantity', 'max_order_quantity']);
        });
    }
};
