<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('number', 24)->unique();
            $table->string('user_id', 36)->index();
            $table->unsignedBigInteger('sku_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedInteger('quantity')->default(1);
            $table->json('requirements');
            $table->string('player_name')->nullable();
            $table->unsignedBigInteger('amount_toman');
            $table->string('cost_usd_snapshot', 32)->default('0');
            $table->string('fx_rate_snapshot', 32)->default('0');
            $table->string('margin_snapshot', 16)->default('0');
            $table->string('status', 24)->index();
            $table->string('idempotency_key', 80)->unique();
            $table->string('body_hash', 64);
            $table->string('customer_message')->nullable();
            $table->json('timeline')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 36)->index();
            $table->string('provider', 32);
            $table->unsignedBigInteger('amount_toman');
            $table->string('status', 24)->index();
            $table->string('authority', 64)->nullable()->index();
            $table->string('ref', 64)->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
        });

        Schema::create('fulfillment_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 36)->index();
            $table->string('supplier', 32)->default('shop2topup');
            $table->string('supplier_order_id', 36)->unique();
            $table->string('expected_unit_price', 32)->nullable();
            $table->string('status', 24)->index();
            $table->string('error_code', 64)->nullable();
            $table->boolean('dry_run')->default(true);
            $table->unsignedInteger('poll_count')->default(0);
            $table->timestamp('last_polled_at')->nullable()->index();
            $table->timestamp('submitted_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 64);
            $table->string('supplier_order_id', 36);
            $table->boolean('signature_valid')->default(true);
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['event', 'supplier_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_webhook_events');
        Schema::dropIfExists('fulfillment_attempts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('orders');
    }
};
