<?php

namespace Tests\Feature;

use App\Models\BuyGuide;
use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Modules\BuyGuide\BuyGuideMarkdownParser;
use App\Modules\Supplier\FazerCards\FazerCardsIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BuyGuideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config([
            'services.storefront.url' => 'http://127.0.0.1:3001',
            'services.storefront.revalidate_secret' => 'test-secret',
        ]);
    }

    public function test_resolve_falls_back_per_section_across_three_levels(): void
    {
        $product = $this->makeGift('giftcard-playstation-us', 'playstation', 'US');
        $token = $this->adminToken();
        Http::fake(['http://127.0.0.1:3001/*' => Http::response(['success' => true], 200)]);

        $this->withToken($token)->postJson('/api/admin/buy-guides', [
            'scopeType' => 'category',
            'category' => 'gift-card',
            'title' => 'راهنمای گیفت کارت',
            'intro' => '<p>مقدمه دسته</p>',
            'status' => 'published',
            'sections' => [
                [
                    'type' => 'purchase_steps',
                    'inherit' => false,
                    'data' => ['steps' => [['text' => 'از دسته: پرداخت کن']]],
                ],
                [
                    'type' => 'faq',
                    'inherit' => false,
                    'data' => ['items' => [['q' => 'سؤال دسته؟', 'a' => 'جواب دسته']]],
                ],
            ],
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/admin/buy-guides', [
            'scopeType' => 'subcategory',
            'category' => 'gift-card',
            'subcategory' => 'playstation',
            'title' => 'گیفت کارت پلی‌استیشن',
            'intro' => '<p>مقدمه پلی‌استیشن</p>',
            'status' => 'published',
            'sections' => [
                [
                    'type' => 'faq',
                    'inherit' => false,
                    'data' => ['items' => [['q' => 'کارت آمریکا روی انگلیس؟', 'a' => 'خیر']]],
                ],
                [
                    'type' => 'purchase_steps',
                    'inherit' => true,
                    'data' => ['steps' => []],
                ],
            ],
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/admin/buy-guides', [
            'scopeType' => 'product',
            'productId' => $product->id,
            'status' => 'published',
            'sections' => [
                [
                    'type' => 'errors',
                    'inherit' => false,
                    'data' => ['items' => [['error' => 'کد نامعتبر', 'fix' => 'دوباره وارد کنید']]],
                ],
            ],
        ])->assertCreated();

        $detail = $this->getJson('/api/catalog/products/giftcard-playstation-us');
        $detail->assertOk()
            ->assertJsonPath('data.buyGuide.title', 'گیفت کارت پلی‌استیشن')
            ->assertJsonPath('data.buyGuide.introHtml', '<p>مقدمه پلی‌استیشن</p>');
        $types = collect($detail->json('data.buyGuide.sections'))->pluck('type')->all();
        $this->assertContains('purchase_steps', $types);
        $this->assertContains('faq', $types);
        $this->assertContains('errors', $types);
        $faq = collect($detail->json('data.buyGuide.sections'))->firstWhere('type', 'faq');
        $this->assertSame('کارت آمریکا روی انگلیس؟', $faq['items'][0]['q']);
        $steps = collect($detail->json('data.buyGuide.sections'))->firstWhere('type', 'purchase_steps');
        $this->assertSame('از دسته: پرداخت کن', $steps['steps'][0]['text']);
        $this->assertSame('category', $steps['source']);
        $this->assertSame('product', collect($detail->json('data.buyGuide.sections'))->firstWhere('type', 'errors')['source']);
    }

    public function test_content_rules_reject_password_price_and_delivery_promise(): void
    {
        $token = $this->adminToken();
        $this->withToken($token)->postJson('/api/admin/buy-guides', [
            'scopeType' => 'category',
            'category' => 'gift-card',
            'title' => 'رمز عبور حساب را وارد کنید',
            'status' => 'draft',
        ])->assertStatus(422);

        $this->withToken($token)->postJson('/api/admin/buy-guides', [
            'scopeType' => 'category',
            'category' => 'game-keys',
            'summary' => 'تحویل فوری در ۵ دقیقه',
            'status' => 'draft',
        ])->assertStatus(422);

        $this->withToken($token)->postJson('/api/admin/buy-guides', [
            'scopeType' => 'category',
            'category' => 'telegram',
            'summary' => 'قیمت از 50000 تومان',
            'status' => 'draft',
        ])->assertStatus(422);
    }

    public function test_upload_without_alt_fails_and_draft_is_hidden_from_public(): void
    {
        Storage::fake('public');
        $product = $this->makeGift('giftcard-xbox-us', 'xbox', 'US');
        $token = $this->adminToken();
        $this->withToken($token)->post('/api/admin/buy-guides/media', [
            'file' => UploadedFile::fake()->image('step.png', 20, 20),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->withToken($token)->postJson('/api/admin/buy-guides', [
            'scopeType' => 'subcategory',
            'category' => 'gift-card',
            'subcategory' => 'xbox',
            'title' => 'پیش‌نویس ایکس‌باکس',
            'intro' => '<p>فقط پیش‌نویس</p>',
            'status' => 'draft',
            'sections' => [
                [
                    'type' => 'faq',
                    'inherit' => false,
                    'data' => ['items' => [['q' => 'مخفی؟', 'a' => 'بله']]],
                ],
            ],
        ])->assertCreated();

        $detail = $this->getJson('/api/catalog/products/'.$product->handle);
        $detail->assertOk();
        $this->assertTrue(
            $detail->json('data.buyGuide') === null
            || ($detail->json('data.buyGuide.introHtml') ?? '') !== '<p>فقط پیش‌نویس</p>'
        );
    }

    public function test_revalidator_is_called_on_publish(): void
    {
        Http::fake(['http://127.0.0.1:3001/api/revalidate' => Http::response(['success' => true], 200)]);
        $token = $this->adminToken();
        $create = $this->withToken($token)->postJson('/api/admin/buy-guides', [
            'scopeType' => 'category',
            'category' => 'steam-gifts',
            'title' => 'گیفت استیم',
            'status' => 'draft',
        ]);
        $create->assertCreated();
        $id = $create->json('data.id');
        $this->withToken($token)->postJson('/api/admin/buy-guides/'.$id.'/publish')->assertOk();
        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:3001/api/revalidate'
                && $request->hasHeader('x-revalidate-secret', 'test-secret')
                && in_array('buy-guide:cat:steam-gifts', $request['tags'] ?? [], true);
        });
    }

    public function test_markdown_parser_reads_persian_section_numbers(): void
    {
        $markdown = <<<'MD'
# راهنمای خرید گیفت کارت پلی‌استیشن در شارژینو

## ۱. خلاصه

در این راهنما گیفت کارت را از شارژینو می‌خرید و در پلتفرم رسمی ردیم می‌کنید.

## ۲. این محصول چیست و برای چه کسی است

کد شارژ کیف پول فروشگاه است.

## ۳. قبل از خرید بدانید

**هشدارها:**
- کد را برای کسی نفرستید.

## ۵. مراحل خرید در شارژینو

1. محصول را انتخاب کنید
2. پرداخت کنید

## ۶. مراحل فعال‌سازی

### PS5
1. تنظیمات حساب را باز کنید

## ۹. سؤالات متداول

**ریجن چیست؟**
باید با کشور حساب یکی باشد.
MD;
        $payload = app(BuyGuideMarkdownParser::class)->parse($markdown, [
            'scope' => 'subcategory',
            'category' => 'gift-card',
            'subcategory' => 'playstation',
        ], sys_get_temp_dir());
        $types = array_column($payload['sections'], 'type');
        $this->assertNotSame('', $payload['summary']);
        $this->assertNotSame('', $payload['intro']);
        $this->assertContains('before_buy', $types);
        $this->assertContains('purchase_steps', $types);
        $this->assertContains('redeem', $types);
        $this->assertContains('faq', $types);
    }

    public function test_markdown_parser_drops_competitor_and_internal_copy(): void
    {
        $markdown = <<<'MD'
# راهنمای خرید یوسی پابجی در شارژینو

## ۱. خلاصه

شارژ مستقیم با **Player ID**.

## ۲. این محصول چیست و برای چه کسی است

برای بازیکنان نسخهٔ جهانی. محصولات «fast» و «reserve» دو مسیر تأمین‌اند.
**محصولات کاتالوگ که این راهنما پوشش می‌دهد:**
- `https://charjino.ir/catalog/topup-pubg-mobile-fast`

## ۹. سؤالات متداول

**آیا رمز لازم است؟**
خیر.

**تفاوت fast و reserve چیست؟**
فقط مسیر تأمین متفاوت است.

## ۱۰. منابع

- Midasbuy: https://www.midasbuy.com/content/how-to-use-new-version-of-midasbuy-v0qj13
- رقیب ایرانی: https://iranmojo.com/addons/uc-automatic/
- FazerCards API (فایل داخلی)
MD;
        $payload = app(BuyGuideMarkdownParser::class)->parse($markdown, [
            'scope' => 'subcategory',
            'category' => 'game-topup',
            'subcategory' => 'pubg-mobile',
        ], sys_get_temp_dir());
        $this->assertStringNotContainsString('**', $payload['intro']);
        $this->assertStringNotContainsString('`', $payload['intro']);
        $this->assertStringNotContainsString('iranmojo', $payload['intro']);
        $this->assertStringNotContainsString('fast', $payload['intro']);
        $faq = collect($payload['sections'])->firstWhere('type', 'faq');
        $questions = array_column($faq['data']['items'] ?? [], 'q');
        $this->assertContains('آیا رمز لازم است؟', $questions);
        $this->assertFalse(collect($questions)->contains(fn ($q) => str_contains(mb_strtolower((string) $q), 'fast')));
        $sources = collect($payload['sections'])->firstWhere('type', 'sources');
        $urls = array_column($sources['data']['items'] ?? [], 'url');
        $this->assertContains('https://www.midasbuy.com/content/how-to-use-new-version-of-midasbuy-v0qj13', $urls);
        $this->assertFalse(collect($urls)->contains(fn ($url) => str_contains((string) $url, 'iranmojo')));
        $html = \App\Modules\BuyGuide\BuyGuidePublicCopy::presentIntroHtml(
            '<p>محصولات «fast» و «reserve».</p><p>برای بازیکنان جهانی با **Player ID**.</p>',
        );
        $this->assertIsString($html);
        $this->assertStringContainsString('Player ID', $html);
        $this->assertStringNotContainsString('**', $html);
        $this->assertStringNotContainsString('fast', $html);
    }

    public function test_markdown_parser_reads_aparat_and_youtube_table_links(): void
    {
        $markdown = <<<'MD'
# راهنمای خرید گیفت کارت پلی‌استیشن در شارژینو

## ۱. خلاصه

در این راهنما گیفت کارت را از شارژینو می‌خرید.

## ۷. ویدیو آموزشی

| ویدیو | کانال | زبان | مدت | وضعیت لینک |
|---|---|---|---|---|
| [نحوه ردیم کردن گیفت کارت](https://www.aparat.com/v/rge45ec) | cafearzz | فارسی | 1:25 | بررسی‌شده |
| [PS5 Redeem](https://www.youtube.com/watch?v=hed6svCiX4c) | WorldofTech | انگلیسی | 2:35 | بررسی‌شده |

### سناریوی ویدیوی شارژینو

| زمان | تصویر | متن گوینده |
|---|---|---|
| ۰–۵ ث | لوگوی شارژینو | «ریجن را چک کنید.» |
MD;
        $payload = app(BuyGuideMarkdownParser::class)->parse($markdown, [
            'scope' => 'subcategory',
            'category' => 'gift-card',
            'subcategory' => 'playstation',
        ], sys_get_temp_dir());
        $videos = collect($payload['sections'])->firstWhere('type', 'videos');
        $this->assertIsArray($videos);
        $this->assertCount(2, $videos['data']['items']);
        $this->assertSame('aparat', $videos['data']['items'][0]['provider']);
        $this->assertSame('https://www.aparat.com/v/rge45ec', $videos['data']['items'][0]['url']);
        $this->assertSame('youtube', $videos['data']['items'][1]['provider']);
        $this->assertSame('en', $videos['data']['items'][1]['lang']);

        $piped = <<<'MD'
# راهنمای شارژ اپ‌های استریم در شارژینو

## ۱. خلاصه

شارژ با شناسهٔ کاربری.

## ۷. ویدیو آموزشی

| عنوان | پلتفرم / کانال | زبان | مدت | توضیح |
|---|---|---|---|---|
| [How to Find Your BIGO ID (2025 Update) | Step-by-Step Guide](https://www.youtube.com/watch?v=OKqE6eHmChs) | YouTube | انگلیسی | 1:19 | Bigo ID |
MD;
        $social = app(BuyGuideMarkdownParser::class)->parse($piped, [
            'scope' => 'subcategory',
            'category' => 'game-topup',
            'subcategory' => 'live-and-social-apps',
        ], sys_get_temp_dir());
        $socialVideos = collect($social['sections'])->firstWhere('type', 'videos');
        $this->assertSame('https://www.youtube.com/watch?v=OKqE6eHmChs', $socialVideos['data']['items'][0]['url']);
    }

    public function test_game_topup_scope_uses_handle_not_parent_group_slug(): void
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'game-topup'],
            ['name' => 'شارژ مستقیم', 'title' => 'شارژ مستقیم', 'visible' => true, 'sort_order' => 10],
        );
        $pubg = $this->makeTopup('topup-pubg-mobile-fast', $group);
        $genshin = $this->makeTopup('topup-genshin-impact-global', $group);
        $yalla = $this->makeTopup('topup-yalla', $group);
        $ludo = $this->makeTopup('topup-yalla-ludo', $group);
        $pool = $this->makeTopup('topup-8-ball-pool', $group);

        $this->assertSame(['category' => 'game-topup', 'subcategory' => 'pubg-mobile'], \App\Modules\BuyGuide\BuyGuideScope::forProduct($pubg));
        $this->assertSame(['category' => 'game-topup', 'subcategory' => 'hoyoverse'], \App\Modules\BuyGuide\BuyGuideScope::forProduct($genshin));
        $this->assertSame(['category' => 'game-topup', 'subcategory' => 'live-and-social-apps'], \App\Modules\BuyGuide\BuyGuideScope::forProduct($yalla));
        $this->assertSame(['category' => 'game-topup', 'subcategory' => 'other-games-generic'], \App\Modules\BuyGuide\BuyGuideScope::forProduct($ludo));
        $this->assertSame(['category' => 'game-topup', 'subcategory' => 'other-games-generic'], \App\Modules\BuyGuide\BuyGuideScope::forProduct($pool));
    }

    public function test_upload_image_requires_alt_and_stores_webp(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('gd webp missing');
        }
        Storage::fake('public');
        $token = $this->adminToken();
        $png = $this->tinyPng();
        $file = UploadedFile::fake()->createWithContent('guide.png', $png);
        $res = $this->withToken($token)->post('/api/admin/buy-guides/media', [
            'alt' => 'صفحه‌ی Redeem در PS5',
            'file' => $file,
        ], ['Accept' => 'application/json']);
        $res->assertOk()->assertJsonPath('data.alt', 'صفحه‌ی Redeem در PS5');
        $this->assertStringContainsString('.webp', (string) $res->json('data.src'));
    }

    private function tinyPng(): string
    {
        $im = imagecreatetruecolor(12, 12);
        imagefilledrectangle($im, 0, 0, 11, 11, imagecolorallocate($im, 5, 99, 252));
        ob_start();
        imagepng($im);
        $data = (string) ob_get_clean();
        imagedestroy($im);

        return $data;
    }

    private function makeTopup(string $handle, CatalogGroup $group): Product
    {
        $product = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => $handle,
            'code' => $handle,
            'title' => $handle,
            'publisher' => '',
            'category_name' => $group->title,
            'subtitle' => 'شارژ',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'visible' => true,
            'status' => 'active',
            'fulfillment_type' => FazerCardsIds::TOPUP,
            'platform' => '',
            'region' => '',
            'inputs' => [],
            'sort_order' => 10,
        ]);

        return $product->load('catalogGroup');
    }

    private function makeGift(string $handle, string $platform, string $region): Product
    {
        $group = CatalogGroup::query()->firstOrCreate(
            ['slug' => 'gift-card'],
            ['name' => 'گیفت کارت', 'title' => 'گیفت کارت', 'visible' => true, 'sort_order' => 20],
        );
        $product = Product::query()->create([
            'catalog_group_id' => $group->id,
            'handle' => $handle,
            'code' => $handle,
            'title' => 'PlayStation '.$region,
            'publisher' => '',
            'category_name' => $group->title,
            'subtitle' => 'کارت',
            'thumbnail' => '/image/catalog/pubg-mobile.svg',
            'visible' => true,
            'status' => 'active',
            'fulfillment_type' => FazerCardsIds::GIFTCARD,
            'platform' => $platform,
            'region' => $region,
            'inputs' => [],
            'sort_order' => 10,
        ]);
        Sku::query()->create([
            'product_id' => $product->id,
            'code' => $handle.'-10',
            'name' => '10',
            'price' => 10000,
            'cost_usd' => '1',
            'status' => 'active',
            'visible' => true,
            'fulfillment_type' => FazerCardsIds::GIFTCARD,
            'region' => $region,
            'sort_order' => 10,
        ]);

        return $product->load('catalogGroup', 'skus');
    }

    private function adminToken(): string
    {
        $login = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@charjino.local',
            'password' => 'charjino-admin',
        ]);
        $login->assertOk();

        return (string) $login->json('data.access_token');
    }
}
