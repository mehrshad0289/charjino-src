<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('excluded_reason', 64)->nullable()->after('visible');
            $table->json('purchase_schema')->nullable()->after('inputs');
            $table->timestamp('synced_at')->nullable()->after('updated_at');
        });

        Schema::table('skus', function (Blueprint $table) {
            $table->unsignedInteger('stock')->nullable()->after('max_order_quantity');
            $table->timestamp('synced_at')->nullable()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['excluded_reason', 'purchase_schema', 'synced_at']);
        });
        Schema::table('skus', function (Blueprint $table) {
            $table->dropColumn(['stock', 'synced_at']);
        });
    }
};
