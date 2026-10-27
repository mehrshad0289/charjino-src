<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('unavailable_at')->nullable()->after('synced_at');
            $table->foreignId('substitute_product_id')->nullable()->after('unavailable_at')->constrained('products')->nullOnDelete();
        });

        Schema::create('url_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('old_path', 255);
            $table->string('target_path', 255)->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->string('reason', 64);
            $table->string('source', 32);
            $table->boolean('active')->default(true);
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->timestamps();

            $table->index(['old_path', 'active']);
            $table->index(['product_id', 'active']);
        });

        Schema::create('catalog_change_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('actor_type', 32);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('source', 64)->nullable();
            $table->string('field', 64);
            $table->text('before')->nullable();
            $table->text('after')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_change_audits');
        Schema::dropIfExists('url_redirects');
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('substitute_product_id');
            $table->dropColumn('unavailable_at');
        });
    }
};
