<?php

use App\Modules\Catalog\CatalogSlug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_groups', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        foreach (DB::table('catalog_groups')->orderBy('id')->get() as $group) {
            $hint = isset($group->shop2_big_category_id) ? (int) $group->shop2_big_category_id : null;
            $slug = CatalogSlug::unique(
                (string) ($group->name ?: $group->title),
                $hint ?: null,
                (int) $group->id,
            );
            DB::table('catalog_groups')->where('id', $group->id)->update(['slug' => $slug]);
        }

        Schema::table('catalog_groups', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_groups', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
