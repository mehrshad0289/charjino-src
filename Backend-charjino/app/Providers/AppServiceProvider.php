<?php

namespace App\Providers;

use App\Events\OrderStatusChanged;
use App\Listeners\NotifyCustomerOnOrderStatus;
use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\SearchTag;
use App\Models\Sku;
use App\Modules\Catalog\CatalogMeiliHandleLookup;
use App\Modules\Catalog\CatalogTextIdResolver;
use App\Modules\Fulfillment\CharjinoFulfillmentGateway;
use App\Modules\Fulfillment\FulfillmentGateway;
use App\Services\Torob\TorobSplitBaselineStore;
use App\Services\Torob\TorobSplitCanonicalStore;
use App\Services\Torob\TorobSplitRuntimeStore;
use App\Observers\CatalogGroupObserver;
use App\Observers\ProductObserver;
use App\Observers\SearchTagObserver;
use App\Observers\SkuObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FulfillmentGateway::class, CharjinoFulfillmentGateway::class);
        $this->app->singleton(CatalogTextIdResolver::class, fn () => new CatalogMeiliHandleLookup);
        $this->app->singleton(TorobSplitRuntimeStore::class);
        $this->app->singleton(TorobSplitCanonicalStore::class);
        $this->app->singleton(TorobSplitBaselineStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Product::observe(ProductObserver::class);
        Sku::observe(SkuObserver::class);
        CatalogGroup::observe(CatalogGroupObserver::class);
        SearchTag::observe(SearchTagObserver::class);
        Event::listen(OrderStatusChanged::class, NotifyCustomerOnOrderStatus::class);
        RateLimiter::for('smart-search', function (Request $request) {
            return Limit::perMinute(120)->by((string) $request->ip());
        });
        RateLimiter::for('torob', function (Request $request) {
            return Limit::perMinute(60)->by((string) $request->ip());
        });
    }
}
