<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('skus', 'sync_failed_count') && Schema::hasColumn('skus', 'last_sync_failed_at')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                'ALTER TABLE skus
                 ADD COLUMN sync_failed_count INT UNSIGNED NOT NULL DEFAULT 0,
                 ADD COLUMN last_sync_failed_at TIMESTAMP NULL DEFAULT NULL,
                 ALGORITHM=INSTANT'
            );

            return;
        }

        Schema::table('skus', function (Blueprint $table) {
            if (! Schema::hasColumn('skus', 'sync_failed_count')) {
                $table->unsignedInteger('sync_failed_count')->default(0);
            }
            if (! Schema::hasColumn('skus', 'last_sync_failed_at')) {
                $table->timestamp('last_sync_failed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('skus', function (Blueprint $table) {
            if (Schema::hasColumn('skus', 'sync_failed_count')) {
                $table->dropColumn('sync_failed_count');
            }
            if (Schema::hasColumn('skus', 'last_sync_failed_at')) {
                $table->dropColumn('last_sync_failed_at');
            }
        });
    }
};
