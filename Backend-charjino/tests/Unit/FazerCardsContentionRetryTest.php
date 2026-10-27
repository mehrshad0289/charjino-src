<?php

namespace Tests\Unit;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Modules\Supplier\FazerCards\FazerCardsContentionRetry;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

class FazerCardsContentionRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_retries_1213_then_commits(): void
    {
        $this->seed();
        $attempts = 0;
        $failed = false;
        $id = FazerCardsContentionRetry::run(function () use (&$attempts) {
            return DB::transaction(function () use (&$attempts) {
                $attempts++;
                $product = $this->makeProduct('retry-ok-'.$attempts);
                if ($attempts < 3) {
                    throw $this->contention(1213);
                }

                return (int) $product->id;
            });
        }, function () use (&$failed): void {
            $failed = true;
        });

        $this->assertFalse($failed);
        $this->assertSame(3, $attempts);
        $this->assertNotNull(Product::query()->find($id));
        $this->assertSame(1, Product::query()->where('handle', 'like', 'retry-ok-%')->count());
    }

    public function test_rolls_back_after_three_1020_failures(): void
    {
        $this->seed();
        $attempts = 0;
        $failed = false;
        $id = FazerCardsContentionRetry::run(function () use (&$attempts) {
            return DB::transaction(function () use (&$attempts) {
                $attempts++;
                $this->makeProduct('retry-fail-'.$attempts);
                throw $this->contention(1020);
            });
        }, function () use (&$failed): void {
            $failed = true;
        });

        $this->assertTrue($failed);
        $this->assertNull($id);
        $this->assertSame(3, $attempts);
        $this->assertSame(0, Product::query()->where('handle', 'like', 'retry-fail-%')->count());
        $this->makeProduct('retry-other');
        $this->assertNotNull(Product::query()->where('handle', 'retry-other')->first());
    }

    private function makeProduct(string $handle): Product
    {
        $group = CatalogGroup::query()->firstOrFail();

        return Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => $handle,
            'code' => $handle,
            'title' => $handle,
            'publisher' => 'Test',
            'category_name' => 'Test',
            'subtitle' => 't',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'visible' => true,
            'status' => 'active',
        ]);
    }

    private function contention(int $code): QueryException
    {
        $pdo = new PDOException('record changed', $code);
        $pdo->errorInfo = ['HY000', $code, 'record changed'];

        return new QueryException('sqlite', 'update products', [], $pdo);
    }
}
