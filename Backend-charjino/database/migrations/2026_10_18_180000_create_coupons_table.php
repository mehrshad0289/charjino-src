<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->string('type', 16);
            $table->unsignedBigInteger('amount')->nullable();
            $table->unsignedTinyInteger('percentage')->nullable();
            $table->unsignedBigInteger('max_percentage_amount')->nullable();
            $table->unsignedBigInteger('min_order_toman')->default(0);
            $table->unsignedInteger('usage_limit_per_user')->default(1);
            $table->unsignedInteger('max_uses')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('expired_at')->nullable()->index();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('coupon_id')->nullable()->after('product_id');
            $table->string('coupon_code', 32)->nullable()->after('coupon_id');
            $table->unsignedBigInteger('subtotal_toman')->nullable()->after('amount_toman');
            $table->unsignedBigInteger('discount_toman')->default(0)->after('subtotal_toman');
            $table->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn(['coupon_id', 'coupon_code', 'subtotal_toman', 'discount_toman']);
        });
        Schema::dropIfExists('coupons');
    }
};
