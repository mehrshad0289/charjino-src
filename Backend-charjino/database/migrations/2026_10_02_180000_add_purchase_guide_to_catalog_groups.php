<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_groups', function (Blueprint $table) {
            $table->text('purchase_guide')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_groups', function (Blueprint $table) {
            $table->dropColumn('purchase_guide');
        });
    }
};
