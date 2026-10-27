<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('indexable')->default(true)->after('visible');
            $table->index('indexable');
        });

        DB::table('products')->where('fulfillment_type', 'steam-gift')->update(['indexable' => false]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['indexable']);
            $table->dropColumn('indexable');
        });
    }
};
