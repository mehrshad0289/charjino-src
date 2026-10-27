<?php

use App\Models\Product;
use App\Models\ProductSupplierIdentity;
use App\Modules\Catalog\ProductNormalizedKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('normalized_key', 191)->default('')->after('handle');
            $table->string('normalized_kind', 16)->default('')->after('normalized_key');
            $table->string('normalized_region', 64)->default('')->after('normalized_kind');
            $table->string('normalized_unique', 220)->nullable()->after('normalized_region');
            $table->index(['normalized_kind', 'normalized_region', 'normalized_key'], 'products_normalized_lookup');
        });

        Schema::create('product_supplier_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('supplier', 32);
            $table->string('remote_id', 160);
            $table->timestamps();
            $table->unique(['supplier', 'remote_id']);
            $table->index(['product_id', 'supplier']);
        });

        Product::query()->orderBy('id')->each(function (Product $product): void {
            ProductNormalizedKey::apply($product);
            $product->saveQuietly();
            if (filled($product->fazercards_category_id)) {
                ProductSupplierIdentity::query()->updateOrCreate(
                    [
                        'supplier' => ProductSupplierIdentity::FAZERCARDS,
                        'remote_id' => (string) $product->fazercards_category_id,
                    ],
                    ['product_id' => $product->id],
                );
            }
            if ($product->shop2_category_id) {
                ProductSupplierIdentity::query()->updateOrCreate(
                    [
                        'supplier' => ProductSupplierIdentity::SHOP2,
                        'remote_id' => (string) $product->shop2_category_id,
                    ],
                    ['product_id' => $product->id],
                );
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique('normalized_unique', 'products_normalized_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_normalized_unique');
            $table->dropIndex('products_normalized_lookup');
            $table->dropColumn(['normalized_key', 'normalized_kind', 'normalized_region', 'normalized_unique']);
        });
        Schema::dropIfExists('product_supplier_identities');
    }
};
