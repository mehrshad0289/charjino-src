<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment_type', 24)->nullable()->index();
            $table->string('hold_reason', 40)->nullable();
            $table->timestamp('hold_until')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('needs_review_reason', 40)->nullable();
            $table->string('guide_slug')->nullable();
            $table->json('notified_statuses')->nullable();
        });

        DB::table('orders')->where('status', 'pending_payment')->update(['status' => 'awaiting_payment']);
        DB::table('orders')->where('status', 'fulfilling')->update(['status' => 'processing']);
        DB::table('orders')->where('status', 'delivered')->update(['status' => 'completed']);
        DB::table('orders')->where('status', 'partial')->update(['status' => 'needs_review']);

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 36)->index();
            $table->unsignedBigInteger('sku_id')->nullable()->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('fulfillment_type', 24)->nullable();
            $table->json('supplier_ref')->nullable();
            $table->json('requirements')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('steam_currency', 8)->nullable();
            $table->string('steam_amount', 32)->nullable();
            $table->unsignedBigInteger('unit_price_toman')->default(0);
            $table->unsignedBigInteger('total_toman')->default(0);
            $table->decimal('unit_cost_usd', 18, 6)->default(0);
            $table->decimal('total_cost_usd', 18, 6)->default(0);
            $table->string('fx_rate', 32)->default('0');
            $table->string('margin_percent', 16)->default('0');
            $table->string('price_source', 16)->default('synced');
            $table->timestamp('priced_at')->nullable();
            $table->integer('stock_at_order')->nullable();
            $table->timestamps();
        });

        $now = now();
        foreach (DB::table('orders')->orderBy('created_at')->cursor() as $order) {
            DB::table('order_items')->insert([
                'order_id' => $order->id,
                'sku_id' => $order->sku_id,
                'product_id' => $order->product_id,
                'fulfillment_type' => $order->fulfillment_type,
                'requirements' => $order->requirements,
                'quantity' => $order->quantity,
                'unit_price_toman' => (int) round(((int) $order->amount_toman) / max(1, (int) $order->quantity)),
                'total_toman' => $order->amount_toman,
                'unit_cost_usd' => $order->cost_usd_snapshot,
                'total_cost_usd' => $order->cost_usd_snapshot,
                'fx_rate' => $order->fx_rate_snapshot,
                'margin_percent' => $order->margin_snapshot,
                'price_source' => 'synced',
                'priced_at' => $order->created_at,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('fulfillment_attempts', function (Blueprint $table) {
            $table->unsignedBigInteger('order_item_id')->nullable()->index();
            $table->unsignedSmallInteger('attempt_no')->default(1);
            $table->string('idempotency_key', 255)->nullable();
            $table->boolean('idempotent')->default(true);
            $table->string('fazer_order_id', 64)->nullable();
            $table->string('fazer_kind', 32)->nullable();
            $table->string('fazer_status', 16)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('fail_reason')->nullable();
            $table->json('request_redacted')->nullable();
            $table->json('response_redacted')->nullable();
            $table->decimal('charged_usd', 18, 6)->nullable();
            $table->timestamp('sending_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('terminal_at')->nullable();
            $table->timestamp('unknown_since')->nullable();
            $table->unsignedInteger('reconcile_count')->default(0);
            $table->timestamp('next_poll_at')->nullable()->index();
            $table->timestamp('last_webhook_at')->nullable();
            $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
            $table->text('resolution_note')->nullable();
        });

        foreach (DB::table('fulfillment_attempts')->orderBy('id')->cursor() as $attempt) {
            $itemId = DB::table('order_items')->where('order_id', $attempt->order_id)->value('id');
            $key = (string) $attempt->supplier_order_id;
            $fazerId = str_starts_with($key, 'ord-') ? $key : null;
            DB::table('fulfillment_attempts')->where('id', $attempt->id)->update([
                'order_item_id' => $itemId,
                'idempotency_key' => $key !== '' ? $key : (string) Str::uuid(),
                'fazer_order_id' => $fazerId,
            ]);
        }

        Schema::table('fulfillment_attempts', function (Blueprint $table) {
            $table->unique('idempotency_key');
            $table->unique('fazer_order_id');
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement("CREATE UNIQUE INDEX fulfillment_attempts_one_active ON fulfillment_attempts (order_item_id) WHERE order_item_id IS NOT NULL AND status IN ('queued','sending','created','processing','unknown')");
        }

        Schema::create('delivered_items', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 36)->index();
            $table->unsignedBigInteger('order_item_id')->nullable()->index();
            $table->unsignedBigInteger('attempt_id')->nullable()->index();
            $table->string('kind', 16);
            $table->unsignedSmallInteger('position')->default(0);
            $table->text('secret');
            $table->char('fingerprint', 64);
            $table->timestamp('first_revealed_at')->nullable();
            $table->unsignedInteger('reveal_count')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->unique(['attempt_id', 'fingerprint']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 24)->index();
            $table->string('event_id', 128);
            $table->string('event', 64);
            $table->string('fazer_order_id', 64)->nullable()->index();
            $table->string('data_type', 32)->nullable();
            $table->string('data_status', 24)->nullable();
            $table->string('previous_status', 24)->nullable();
            $table->unsignedSmallInteger('delivery_attempt')->nullable();
            $table->boolean('signature_valid')->default(true);
            $table->json('payload')->nullable();
            $table->unsignedBigInteger('attempt_id')->nullable()->index();
            $table->string('status', 16)->default('received');
            $table->string('process_error')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('order_transitions', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 36)->index();
            $table->string('from', 24)->nullable();
            $table->string('to', 24);
            $table->string('actor', 64)->default('system');
            $table->string('code', 64)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 36)->index();
            $table->unsignedBigInteger('amount_toman');
            $table->string('method', 24);
            $table->string('status', 16)->index();
            $table->string('reason_code', 40)->nullable();
            $table->string('requested_by', 64)->nullable();
            $table->unsignedBigInteger('decided_by_admin_id')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedBigInteger('wallet_transaction_id')->nullable();
            $table->string('external_ref', 80)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->string('user_id', 36)->primary();
            $table->unsignedBigInteger('balance_toman')->default(0);
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 36)->index();
            $table->string('type', 16);
            $table->unsignedBigInteger('amount_toman');
            $table->unsignedBigInteger('balance_after');
            $table->string('reason', 32);
            $table->string('order_id', 36)->nullable()->index();
            $table->unsignedBigInteger('refund_id')->nullable();
            $table->string('idempotency_key', 80)->unique();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('admin_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('dedupe_key', 80)->index();
            $table->json('payload')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_alerts');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('order_transitions');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('delivered_items');
        Schema::dropIfExists('order_items');
        Schema::table('fulfillment_attempts', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropUnique(['fazer_order_id']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'fulfillment_type',
                'hold_reason',
                'hold_until',
                'paid_at',
                'completed_at',
                'failed_at',
                'refunded_at',
                'needs_review_reason',
                'guide_slug',
                'notified_statuses',
            ]);
        });
    }
};
