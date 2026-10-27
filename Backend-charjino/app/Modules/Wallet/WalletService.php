<?php

namespace App\Modules\Wallet;

use App\Exceptions\ApiException;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\CustomerError;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function wallet(string $userId): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['user_id' => $userId],
            ['balance_toman' => 0],
        );
    }

    public function credit(string $userId, int $amount, string $reason, string $idempotencyKey, ?string $orderId = null, ?int $refundId = null): WalletTransaction
    {
        if ($amount < 1) {
            throw new ApiException(CustomerError::message('SKU_UNAVAILABLE'), 400, 'SKU_UNAVAILABLE');
        }

        return DB::transaction(function () use ($userId, $amount, $reason, $idempotencyKey, $orderId, $refundId) {
            $existing = WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
            $wallet = Wallet::query()->where('user_id', $userId)->lockForUpdate()->first();
            if (! $wallet) {
                $wallet = new Wallet;
                $wallet->user_id = $userId;
                $wallet->balance_toman = 0;
            }
            $wallet->balance_toman = (int) $wallet->balance_toman + $amount;
            $wallet->save();

            return WalletTransaction::query()->create([
                'user_id' => $userId,
                'type' => WalletTransaction::TYPE_CREDIT,
                'amount_toman' => $amount,
                'balance_after' => $wallet->balance_toman,
                'reason' => $reason,
                'order_id' => $orderId,
                'refund_id' => $refundId,
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
            ]);
        });
    }

    public function debit(string $userId, int $amount, string $reason, string $idempotencyKey, ?string $orderId = null): WalletTransaction
    {
        if ($amount < 1) {
            throw new ApiException(CustomerError::message('SKU_UNAVAILABLE'), 400, 'SKU_UNAVAILABLE');
        }

        return DB::transaction(function () use ($userId, $amount, $reason, $idempotencyKey, $orderId) {
            $existing = WalletTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
            $wallet = Wallet::query()->where('user_id', $userId)->lockForUpdate()->first();
            if (! $wallet || (int) $wallet->balance_toman < $amount) {
                throw new ApiException(CustomerError::message('WALLET_INSUFFICIENT'), 400, 'WALLET_INSUFFICIENT');
            }
            $wallet->balance_toman = (int) $wallet->balance_toman - $amount;
            $wallet->save();

            return WalletTransaction::query()->create([
                'user_id' => $userId,
                'type' => WalletTransaction::TYPE_DEBIT,
                'amount_toman' => $amount,
                'balance_after' => $wallet->balance_toman,
                'reason' => $reason,
                'order_id' => $orderId,
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * @return array{balanceToman: int, transactions: list<array<string, mixed>>, note: string}
     */
    public function customerPayload(string $userId): array
    {
        $wallet = $this->wallet($userId);
        $rows = WalletTransaction::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (WalletTransaction $row) => [
                'type' => $row->type,
                'amountToman' => $row->amount_toman,
                'balanceAfter' => $row->balance_after,
                'reason' => $row->reason,
                'orderId' => $row->order_id,
                'createdAt' => $row->created_at?->toIso8601String(),
            ])
            ->all();

        return [
            'balanceToman' => (int) $wallet->balance_toman,
            'transactions' => $rows,
            'note' => 'اعتبار کیف پول شارژینو فقط برای خرید در شارژینو قابل استفاده است.',
        ];
    }
}
