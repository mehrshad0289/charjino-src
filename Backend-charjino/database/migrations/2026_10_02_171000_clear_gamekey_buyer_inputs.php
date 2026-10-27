<?php

use App\Modules\Catalog\StorefrontHomeCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->where('fulfillment_type', 'gamekey')->update(['inputs' => json_encode([])]);
        StorefrontHomeCache::reset();
    }

    public function down(): void
    {
        DB::table('products')->where('fulfillment_type', 'gamekey')->where('inputs', json_encode([]))->update([
            'inputs' => json_encode(['playerId']),
        ]);
    }
};
