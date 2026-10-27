<?php

use App\Http\Controllers\Api\Admin\AdminBlogController;
use App\Http\Controllers\Api\Admin\AdminBuyGuideController;
use App\Http\Controllers\Api\Admin\AdminCatalogController;
use App\Http\Controllers\Api\Admin\AdminCouponController;
use App\Http\Controllers\Api\Admin\AdminFazerCardsController;
use App\Http\Controllers\Api\Admin\AdminHomeController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AdminProductCommentController;
use App\Http\Controllers\Api\Admin\AdminSearchController;
use App\Http\Controllers\Api\Admin\AdminSettingsController;
use App\Http\Controllers\Api\Admin\AdminSupplierController;
use App\Http\Controllers\Api\Admin\AdminTicketController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CheckoutCouponController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\FazerCardsWebhookController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentCallbackController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\ProductCommentController;
use App\Http\Controllers\Api\Shop2WebhookController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Torob\TorobProductController;
use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\OptionalJwt;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::get('/platform/policy', [PlatformController::class, 'policy']);

Route::post('/auth/request-otp', [AuthController::class, 'requestOtp'])->middleware('throttle:10,10');
Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:20,10');
Route::post('/auth/refresh', [AuthController::class, 'refresh']);
Route::post('/auth/admin/login', [AuthController::class, 'adminLogin'])->middleware('throttle:10,10');
Route::post('/auth/admin/forgot-password', [AuthController::class, 'forgotAdminPassword'])->middleware('throttle:5,10');
Route::post('/auth/admin/reset-password', [AuthController::class, 'resetAdminPassword'])->middleware('throttle:8,10');

Route::post('/torob/v3/products', [TorobProductController::class, 'products'])
    ->middleware(['torob.jwt', 'throttle:torob']);

Route::middleware(AuthenticateJwt::class)->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);

    Route::middleware(EnsureAdmin::class)->prefix('admin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/settings', [AdminSettingsController::class, 'show']);
        Route::put('/settings', [AdminSettingsController::class, 'update']);
        Route::post('/settings/fx', [AdminSettingsController::class, 'refreshFx']);
        Route::post('/uploads', [AdminSettingsController::class, 'upload']);
        Route::get('/buy-guides/coverage', [AdminBuyGuideController::class, 'coverage']);
        Route::get('/buy-guides/resolve-preview', [AdminBuyGuideController::class, 'resolvePreview']);
        Route::get('/buy-guides/region-options', [AdminBuyGuideController::class, 'regionOptions']);
        Route::post('/buy-guides/media', [AdminBuyGuideController::class, 'uploadMedia']);
        Route::patch('/buy-guides/media/{media}', [AdminBuyGuideController::class, 'updateMedia']);
        Route::get('/buy-guides', [AdminBuyGuideController::class, 'index']);
        Route::post('/buy-guides', [AdminBuyGuideController::class, 'store']);
        Route::get('/buy-guides/{guide}', [AdminBuyGuideController::class, 'show']);
        Route::put('/buy-guides/{guide}', [AdminBuyGuideController::class, 'update']);
        Route::delete('/buy-guides/{guide}', [AdminBuyGuideController::class, 'destroy']);
        Route::post('/buy-guides/{guide}/duplicate', [AdminBuyGuideController::class, 'duplicate']);
        Route::post('/buy-guides/{guide}/publish', [AdminBuyGuideController::class, 'publish']);
        Route::post('/buy-guides/{guide}/unpublish', [AdminBuyGuideController::class, 'unpublish']);
        Route::get('/supplier', [AdminSupplierController::class, 'show']);
        Route::post('/supplier/discover', [AdminSupplierController::class, 'discover']);
        Route::get('/supplier/sku-map', [AdminSupplierController::class, 'skuMap']);
        Route::get('/supplier/price', [AdminSupplierController::class, 'price']);
        Route::post('/supplier/validate-player', [AdminSupplierController::class, 'validatePlayer']);
        Route::get('/fazercards', [AdminFazerCardsController::class, 'show']);
        Route::put('/fazercards/key', [AdminFazerCardsController::class, 'saveKey']);
        Route::delete('/fazercards/key', [AdminFazerCardsController::class, 'clearKey']);
        Route::put('/fazercards/settings', [AdminFazerCardsController::class, 'saveSettings']);
        Route::put('/fazercards/steam-gift-blocklist', [AdminFazerCardsController::class, 'saveSteamGiftBlocklist']);
        Route::get('/fazercards/audits', [AdminFazerCardsController::class, 'audits']);
        Route::post('/fazercards/sync', [AdminFazerCardsController::class, 'sync']);
        Route::post('/fazercards/sync/cancel', [AdminFazerCardsController::class, 'cancelSync']);
        Route::get('/fazercards/sync', [AdminFazerCardsController::class, 'syncStatus']);
        Route::get('/fazercards/steam-gifts/search', [AdminFazerCardsController::class, 'searchSteamGifts']);
        Route::post('/fazercards/steam-gifts/import', [AdminFazerCardsController::class, 'importSteamGift']);
        Route::get('/fazercards/catalog/search', [AdminFazerCardsController::class, 'searchCatalog']);
        Route::post('/fazercards/catalog/import', [AdminFazerCardsController::class, 'importCatalog']);
        Route::match(
            ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
            '/fazercards/proxy/{path}',
            [AdminFazerCardsController::class, 'proxy']
        )->where('path', '.*');
        Route::get('/catalog/groups', [AdminCatalogController::class, 'groups']);
        Route::post('/catalog/groups', [AdminCatalogController::class, 'storeGroup']);
        Route::delete('/catalog/groups/unlinked', [AdminCatalogController::class, 'purgeUnlinkedGroups']);
        Route::delete('/catalog/shop2', [AdminCatalogController::class, 'purgeShop2Catalog']);
        Route::get('/catalog/groups/{group}', [AdminCatalogController::class, 'group']);
        Route::put('/catalog/groups/{group}', [AdminCatalogController::class, 'updateGroup']);
        Route::delete('/catalog/groups/{group}', [AdminCatalogController::class, 'destroyGroup']);
        Route::get('/catalog/products', [AdminCatalogController::class, 'products']);
        Route::get('/catalog/excluded', [AdminCatalogController::class, 'excluded']);
        Route::post('/catalog/products', [AdminCatalogController::class, 'storeProduct']);
        Route::get('/catalog/gift-cards', [AdminCatalogController::class, 'giftCards']);
        Route::get('/catalog/products/{product}', [AdminCatalogController::class, 'product']);
        Route::put('/catalog/products/{product}', [AdminCatalogController::class, 'updateProduct']);
        Route::delete('/catalog/products/{product}', [AdminCatalogController::class, 'hideProduct']);
        Route::post('/catalog/products/{product}/refresh-requirements', [AdminCatalogController::class, 'refreshRequirements']);
        Route::post('/catalog/products/{product}/skus', [AdminCatalogController::class, 'storeSku']);
        Route::put('/catalog/skus/{sku}', [AdminCatalogController::class, 'updateSku']);
        Route::get('/home', [AdminHomeController::class, 'show']);
        Route::put('/home/copy', [AdminHomeController::class, 'updateCopy']);
        Route::put('/home/sections', [AdminHomeController::class, 'updateSections']);
        Route::post('/banners', [AdminHomeController::class, 'storeBanner']);
        Route::put('/banners/{banner}', [AdminHomeController::class, 'updateBanner']);
        Route::delete('/banners/{banner}', [AdminHomeController::class, 'destroyBanner']);
        Route::post('/flash-sale', [AdminHomeController::class, 'storeFlashSale']);
        Route::put('/flash-sale/{flashSaleItem}', [AdminHomeController::class, 'updateFlashSale']);
        Route::delete('/flash-sale/{flashSaleItem}', [AdminHomeController::class, 'destroyFlashSale']);
        Route::get('/tickets', [AdminTicketController::class, 'index']);
        Route::get('/tickets/{ticket}', [AdminTicketController::class, 'show']);
        Route::post('/tickets/{ticket}/messages', [AdminTicketController::class, 'reply']);
        Route::post('/tickets/{ticket}/status', [AdminTicketController::class, 'setStatus']);
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
        Route::post('/orders/{order}/retry-fulfillment', [AdminOrderController::class, 'retryFulfillment']);
        Route::post('/orders/{order}/redeliver', [AdminOrderController::class, 'redeliver']);
        Route::post('/orders/{order}/refresh', [AdminOrderController::class, 'refresh']);
        Route::post('/orders/{order}/link', [AdminOrderController::class, 'link']);
        Route::post('/orders/{order}/abandon', [AdminOrderController::class, 'abandon']);
        Route::post('/orders/{order}/refund', [AdminOrderController::class, 'refund']);
        Route::post('/orders/{order}/reveal', [AdminOrderController::class, 'reveal']);
        Route::post('/orders/{order}/mark-refunded', [AdminOrderController::class, 'markRefunded']);
        Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancel']);
        Route::get('/alerts', [AdminOrderController::class, 'alerts']);
        Route::post('/alerts/{alert}/resolve', [AdminOrderController::class, 'resolveAlert']);
        Route::get('/dashboard/summary', [AdminOrderController::class, 'summary']);
        Route::get('/search/synonyms', [AdminSearchController::class, 'synonyms']);
        Route::post('/search/synonyms', [AdminSearchController::class, 'storeSynonym']);
        Route::put('/search/synonyms/{synonym}', [AdminSearchController::class, 'updateSynonym']);
        Route::delete('/search/synonyms/{synonym}', [AdminSearchController::class, 'destroySynonym']);
        Route::get('/search/tags', [AdminSearchController::class, 'tags']);
        Route::put('/search/tags/{tag}', [AdminSearchController::class, 'updateTag']);
        Route::get('/search/health', [AdminSearchController::class, 'health']);
        Route::post('/search/sync-synonyms', [AdminSearchController::class, 'syncSynonyms']);
        Route::get('/blog/categories', [AdminBlogController::class, 'categories']);
        Route::post('/blog/categories', [AdminBlogController::class, 'storeCategory']);
        Route::put('/blog/categories/{category}', [AdminBlogController::class, 'updateCategory']);
        Route::delete('/blog/categories/{category}', [AdminBlogController::class, 'destroyCategory']);
        Route::get('/blog/tags', [AdminBlogController::class, 'tags']);
        Route::post('/blog/tags', [AdminBlogController::class, 'storeTag']);
        Route::put('/blog/tags/{tag}', [AdminBlogController::class, 'updateTag']);
        Route::delete('/blog/tags/{tag}', [AdminBlogController::class, 'destroyTag']);
        Route::get('/blog/posts/slug-available', [AdminBlogController::class, 'slugAvailable']);
        Route::get('/blog/link-search', [AdminBlogController::class, 'linkSearch']);
        Route::post('/blog/uploads', [AdminBlogController::class, 'upload']);
        Route::get('/blog/posts', [AdminBlogController::class, 'posts']);
        Route::post('/blog/posts', [AdminBlogController::class, 'store']);
        Route::get('/blog/posts/{post}', [AdminBlogController::class, 'show']);
        Route::put('/blog/posts/{post}', [AdminBlogController::class, 'update']);
        Route::delete('/blog/posts/{post}', [AdminBlogController::class, 'destroy']);
        Route::post('/blog/posts/{post}/publish', [AdminBlogController::class, 'publish']);
        Route::post('/blog/posts/{post}/unpublish', [AdminBlogController::class, 'unpublish']);
        Route::get('/blog/posts/{post}/revisions', [AdminBlogController::class, 'revisions']);
        Route::post('/blog/posts/{post}/revisions/{revision}/restore', [AdminBlogController::class, 'restoreRevision']);
        Route::get('/blog/comments', [AdminBlogController::class, 'comments']);
        Route::patch('/blog/comments/{comment}', [AdminBlogController::class, 'updateComment']);
        Route::get('/catalog/comments', [AdminProductCommentController::class, 'index']);
        Route::patch('/catalog/comments/{comment}', [AdminProductCommentController::class, 'update']);
        Route::get('/coupons', [AdminCouponController::class, 'index']);
        Route::post('/coupons', [AdminCouponController::class, 'store']);
        Route::get('/coupons/{coupon}', [AdminCouponController::class, 'show']);
        Route::put('/coupons/{coupon}', [AdminCouponController::class, 'update']);
        Route::delete('/coupons/{coupon}', [AdminCouponController::class, 'destroy']);
    });

    Route::post('/checkout/validate-player', [CheckoutController::class, 'validatePlayer'])->middleware('throttle:10,1');
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{number}', [OrderController::class, 'show']);
    Route::post('/orders/{number}/pay', [OrderController::class, 'pay']);
    Route::post('/orders/{number}/reveal', [OrderController::class, 'reveal'])->middleware('throttle:5,1');
    Route::post('/orders/{number}/refresh', [OrderController::class, 'refresh'])->middleware('throttle:2,1');
    Route::get('/me/wallet', [OrderController::class, 'wallet']);

    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::post('/tickets/{ticket}/messages', [TicketController::class, 'reply']);
    Route::post('/blog/posts/{slug}/comments', [BlogController::class, 'storeComment'])->middleware('throttle:10,1');
    Route::post('/blog/posts/{slug}/like', [BlogController::class, 'likePost'])->middleware('throttle:30,1');
    Route::post('/blog/comments/{comment}/like', [BlogController::class, 'likeComment'])->middleware('throttle:30,1');
    Route::post('/catalog/products/{handle}/comments', [ProductCommentController::class, 'store'])->middleware('throttle:10,1');
    Route::post('/catalog/comments/{comment}/like', [ProductCommentController::class, 'like'])->middleware('throttle:30,1');

    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store']);
    Route::post('/checkout/coupon', [CheckoutCouponController::class, 'preview'])->middleware('throttle:20,1');
    Route::delete('/favorites/{product}', [FavoriteController::class, 'destroy'])->whereNumber('product');
});

Route::match(['GET', 'POST'], '/payments/callback/{provider}', [PaymentCallbackController::class, 'handle']);
Route::match(['GET', 'POST'], '/payments/verify/zibal', [PaymentCallbackController::class, 'verifyZibal'])->middleware('throttle:30,1');
Route::match(['GET', 'POST'], '/payments/verify/zarinpal', [PaymentCallbackController::class, 'verifyZarinpal'])->middleware('throttle:30,1');
Route::post('/webhooks/shop2topup', [Shop2WebhookController::class, 'handle']);
Route::post('/webhooks/fazercards', [FazerCardsWebhookController::class, 'handle']);

Route::get('/catalog/home', [CatalogController::class, 'home']);
Route::get('/catalog/smart-search', [CatalogController::class, 'smartSearch'])->middleware('throttle:smart-search');
Route::get('/catalog/steam-cover/{appId}', [CatalogController::class, 'steamCover'])
    ->whereNumber('appId')
    ->middleware('throttle:120,1');
Route::get('/catalog/categories', [CatalogController::class, 'categories']);
Route::get('/catalog/categories/{slug}', [CatalogController::class, 'category']);

Route::get('/blog', [BlogController::class, 'hub']);
Route::get('/blog/sitemap', [BlogController::class, 'sitemap']);
Route::get('/blog/posts', [BlogController::class, 'posts']);
Route::get('/blog/categories', [BlogController::class, 'categories']);
Route::get('/blog/categories/{slug}', [BlogController::class, 'category']);
Route::get('/blog/tags/{slug}', [BlogController::class, 'tag']);
Route::get('/blog/authors/{slug}', [BlogController::class, 'author']);
Route::get('/blog/preview/{token}', [BlogController::class, 'preview'])->middleware('throttle:30,1');
Route::get('/blog/posts/{slug}', [BlogController::class, 'show'])->middleware(OptionalJwt::class);
Route::get('/blog/posts/{slug}/comments', [BlogController::class, 'comments'])->middleware(OptionalJwt::class);
Route::get('/catalog/products/{handle}/comments', [ProductCommentController::class, 'index'])->middleware(OptionalJwt::class);
Route::get('/catalog/products/{handle}/prices', [CatalogController::class, 'productPrices']);

Route::get('/catalog/resolve', [CatalogController::class, 'resolve']);
Route::get('/catalog/products', [CatalogController::class, 'products']);
Route::get('/catalog/steam-topup/quote', [CatalogController::class, 'steamTopupQuote'])->middleware('throttle:30,1');
Route::get('/catalog/products/{handle}', [CatalogController::class, 'product']);
