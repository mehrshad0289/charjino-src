<?php

namespace Tests\Unit;

use App\Models\CatalogGroup;
use App\Models\Product;
use App\Models\Sku;
use App\Services\Torob\TorobFeedCatalogCopy;
use App\Services\Torob\TorobProductMapper;
use App\Services\Torob\TorobSplitDecision;
use App\Services\Torob\TorobSplitPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TorobSkuTitlesV2Test extends TestCase
{
    use RefreshDatabase;

    private TorobFeedCatalogCopy $copy;

    private TorobProductMapper $mapper;

    private TorobSplitPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'torob.shop_base_url' => 'https://charjino.ir',
            'torob.split_by_sku.enabled_product_ids' => [],
            'torob.split_by_sku_product_ids' => [],
            'torob.split_by_sku.enabled_group_slugs' => [],
            'torob.split_by_sku.exclude_product_ids' => [],
            'torob.split_by_sku.max_items_per_product' => 30,
            'torob.split_by_sku.max_feed_multiplier' => 1.5,
            'torob.split_by_sku.max_stage_extra_rows' => 1500,
            'torob.split_by_sku.canonical_path' => storage_path('framework/testing/torob-canonical-v2.json'),
            'torob.split_by_sku.runtime_path' => storage_path('framework/testing/torob-runtime-v2.json'),
            'torob.split_by_sku.baseline_path' => storage_path('framework/testing/torob-baseline-v2.json'),
        ]);
        @unlink(storage_path('framework/testing/torob-canonical-v2.json'));
        @unlink(storage_path('framework/testing/torob-runtime-v2.json'));
        @unlink(storage_path('framework/testing/torob-baseline-v2.json'));
        $this->copy = $this->app->make(TorobFeedCatalogCopy::class);
        $this->mapper = $this->app->make(TorobProductMapper::class);
        $this->policy = $this->app->make(TorobSplitPolicy::class);
    }

    public function test_t1_decimal_amounts_are_kept_in_gift_card_titles(): void
    {
        $group = $this->group('gift-card');
        $roblox = $this->product($group, 'giftcard-roblox-au', 'Roblox AU', [
            ['name' => '10.37 AUD', 'offer' => '10.37_aud', 'price' => 100000],
            ['name' => '20.78 AUD', 'offer' => '20.78_aud', 'price' => 200000],
        ]);
        $valorant = $this->product($group, 'giftcard-valorant-my', 'Valorant MY', [
            ['name' => '12.90 MYR', 'offer' => '12.90_myr', 'price' => 150000],
            ['name' => '25.90 MYR', 'offer' => '25.90_myr', 'price' => 250000],
        ]);
        $this->enable([$roblox->id, $valorant->id]);

        $robloxTitles = $this->titles($roblox);
        $this->assertSame('گیفت کارت روبلاکس استرالیا 10.37 دلار (AU)', $robloxTitles[0]);
        $this->assertSame('گیفت کارت روبلاکس استرالیا 20.78 دلار (AU)', $robloxTitles[1]);
        $this->assertStringContainsString('10.37', $robloxTitles[0]);
        $this->assertStringNotContainsString('37 دلار', str_replace('10.37 دلار', '', $robloxTitles[0]));

        $valorantTitles = $this->titles($valorant);
        $this->assertSame('گیفت کارت ولورانت مالزی 12.90 رینگیت (MY)', $valorantTitles[0]);
        $this->assertSame('گیفت کارت ولورانت مالزی 25.90 رینگیت (MY)', $valorantTitles[1]);
        $this->assertCount(2, array_unique($valorantTitles));
    }

    public function test_t1_comma_thousands_and_other_decimals(): void
    {
        $group = $this->group('game-topup');
        $ff = $this->product($group, 'topup-free-fire', 'Free Fire', [
            ['name' => '1,041 Diamonds', 'price' => 100000],
            ['name' => '310 Diamonds', 'price' => 200000],
        ]);
        $this->assertSame('شارژ فری فایر 1041 الماس', $this->copy->persianSkuTitle($ff->fresh(['skus', 'catalogGroup']), $ff->skus->first()));
        $gift = $this->group('gift-card');
        $cases = [
            ['giftcard-google-play-my', '12.90 MYR', '12.90_myr', 'گیفت کارت گوگل پلی مالزی 12.90 رینگیت (MY)'],
            ['giftcard-playstation-bh', '19.7 BHD', '19.7_bhd', 'گیفت کارت پلی استیشن بحرین 19.7 دینار (BH)'],
            ['giftcard-steam-sg', '128.98 SGD', '128.98_sgd', 'گیفت کارت استیم سنگاپور 128.98 دلار (SG)'],
        ];
        foreach ($cases as [$handle, $name, $offer, $expected]) {
            $product = $this->product($gift, $handle, $handle, [
                ['name' => $name, 'offer' => $offer, 'price' => 100000],
            ]);
            $sku = $product->skus->first();
            $this->assertSame($expected, $this->copy->persianSkuTitle($product->fresh(['catalogGroup']), $sku), $handle);
        }
    }

    public function test_t2_cod_mobile_bonus_stays_in_title_and_nine_titles_are_unique(): void
    {
        $group = $this->group('game-topup');
        $names = [
            '80 CP',
            '80 + 8 CP',
            '400 CP',
            '400 + 40 CP',
            '800 CP',
            '2000 CP',
            '4000 CP',
            '8000 CP',
            '10000 CP',
        ];
        $skus = [];
        foreach ($names as $i => $name) {
            $skus[] = ['name' => $name, 'price' => ($i + 1) * 10000];
        }
        $product = $this->product($group, 'cod-mobile', 'COD Mobile', $skus);
        $this->enable([$product->id]);
        $fresh = $product->fresh(['skus', 'catalogGroup']);
        $this->assertTrue($this->policy->decide($fresh)->split);
        $titles = $this->titles($product);
        $this->assertCount(9, $titles);
        $this->assertCount(9, array_unique($titles));
        $this->assertContains('شارژ کالاف دیوتی موبایل 80 + 8 سی‌پی', $titles);
        $this->assertContains('شارژ کالاف دیوتی موبایل 80 سی‌پی', $titles);
    }

    public function test_t3_legacy_of_discord_and_ml_adventure_do_not_split(): void
    {
        $group = $this->group('game-topup');
        $discord = $this->product($group, 'topup-legacy-of-discord-furiouswings', 'Legacy of Discord', [
            ['name' => '100 Diamonds', 'price' => 100000],
            ['name' => '500 Diamonds', 'price' => 200000],
        ]);
        $adventure = $this->product($group, 'topup-mobile-legends-adventure', 'ML Adventure', [
            ['name' => '100 M-CASH', 'price' => 100000],
            ['name' => '500 M-CASH', 'price' => 200000],
        ]);
        $this->enable([$discord->id, $adventure->id]);
        $this->assertNull($this->copy->brandNeedle($discord->fresh(['catalogGroup'])));
        $this->assertNull($this->copy->brandNeedle($adventure->fresh(['catalogGroup'])));
        $this->assertSame(TorobSplitDecision::REASON_UNKNOWN_MAPPING, $this->policy->decide($discord->fresh(['skus', 'catalogGroup']))->reason);
        $this->assertSame(TorobSplitDecision::REASON_UNKNOWN_MAPPING, $this->policy->decide($adventure->fresh(['skus', 'catalogGroup']))->reason);
        $this->assertCount(1, $this->mapper->toRows($discord->fresh(['skus', 'catalogGroup'])));
        $this->assertCount(1, $this->mapper->toRows($adventure->fresh(['skus', 'catalogGroup'])));
    }

    public function test_t4_pubg_variants_and_valorant_regions_have_unique_titles(): void
    {
        $group = $this->group('game-topup');
        $ids = [];
        $uc = ['60 UC', '325 UC', '660 UC', '1800 UC', '3850 UC', '8100 UC'];
        foreach (['fast' => 'تحویل سریع', 'manual' => 'تحویل دستی', 'reserve' => 'تحویل رزرو'] as $suffix => $label) {
            $skus = [];
            foreach ($uc as $i => $name) {
                $skus[] = ['name' => $name, 'price' => ($i + 1) * 10000];
            }
            $product = $this->product($group, 'topup-pubg-mobile-'.$suffix, 'PUBG '.$suffix, $skus);
            $ids[] = $product->id;
            foreach ($product->skus as $sku) {
                $title = $this->copy->persianSkuTitle($product->fresh(['catalogGroup']), $sku);
                $this->assertNotNull($title, $product->handle.' '.$sku->name);
                $this->assertStringContainsString($label, (string) $title);
            }
        }
        $vp = ['475 VP', '1000 VP', '2050 VP', '3650 VP', '5350 VP', '11000 VP'];
        $regions = [
            'id' => 'اندونزی',
            'kh' => 'کامبوج',
            'my' => 'مالزی',
            'ph' => 'فیلیپین',
            'sg' => 'سنگاپور',
            'th' => 'تایلند',
        ];
        $khId = null;
        foreach ($regions as $code => $fa) {
            $skus = [];
            foreach ($vp as $i => $name) {
                $skus[] = ['name' => $name, 'price' => ($i + 1) * 10000];
            }
            $product = $this->product($group, 'topup-valorant-'.$code, 'Valorant '.$code, $skus);
            $ids[] = $product->id;
            if ($code === 'kh') {
                $khId = $product->id;
            }
        }
        $this->enable($ids);
        $titles = [];
        foreach ($ids as $id) {
            $product = Product::query()->with(['skus', 'catalogGroup'])->findOrFail($id);
            $this->assertTrue($this->policy->decide($product)->split, $product->handle);
            foreach ($this->mapper->toRows($product) as $row) {
                $titles[] = $row['title'];
            }
        }
        $this->assertCount(54, $titles);
        $this->assertCount(54, array_unique($titles));
        $this->assertContains('شارژ پابجی موبایل 60 یوسی تحویل سریع', $titles);
        $this->assertContains('شارژ پابجی موبایل 60 یوسی تحویل رزرو', $titles);
        $this->assertContains('شارژ ولورانت 475 ولورانت پوینت کامبوج (KH)', $titles);
        $kh = Product::query()->with(['skus', 'catalogGroup'])->findOrFail($khId);
        $this->assertSame(
            'شارژ ولورانت 475 ولورانت پوینت کامبوج (KH)',
            $this->copy->persianSkuTitle($kh, $kh->skus->firstWhere('name', '475 VP')),
        );
    }

    public function test_t5_and_t6_split_titles_are_unique_and_persian_except_region_code(): void
    {
        $gift = $this->group('gift-card');
        $topup = $this->group('game-topup');
        $products = [
            $this->product($gift, 'giftcard-google-play-us', 'GP US', [
                ['name' => '10 USD', 'offer' => '10_usd', 'price' => 100000],
                ['name' => '25 USD', 'offer' => '25_usd', 'price' => 200000],
            ]),
            $this->product($gift, 'giftcard-roblox-au', 'Roblox AU', [
                ['name' => '10.37 AUD', 'offer' => '10.37_aud', 'price' => 100000],
                ['name' => '20.78 AUD', 'offer' => '20.78_aud', 'price' => 200000],
            ]),
            $this->product($topup, 'topup-valorant-id', 'Valorant ID', [
                ['name' => '475 VP', 'price' => 100000],
                ['name' => '1000 VP', 'price' => 200000],
            ]),
            $this->product($topup, 'topup-valorant-ph', 'Valorant PH', [
                ['name' => '475 VP', 'price' => 100000],
                ['name' => '1000 VP', 'price' => 200000],
            ]),
            $this->product($topup, 'cod-mobile', 'COD', [
                ['name' => '80 CP', 'price' => 100000],
                ['name' => '80 + 8 CP', 'price' => 110000],
            ]),
        ];
        $this->enable(array_map(fn (Product $p) => $p->id, $products));
        $keys = [];
        foreach ($products as $product) {
            $fresh = $product->fresh(['skus', 'catalogGroup']);
            $this->assertTrue($this->policy->decide($fresh)->split, $product->handle);
            foreach ($this->mapper->toRows($fresh) as $row) {
                $title = (string) $row['title'];
                $body = preg_replace('/\s*\([A-Z]{2,8}\)$/', '', $title) ?? $title;
                $this->assertDoesNotMatchRegularExpression('/[A-Za-z]/u', $body, $title);
                $key = $this->copy->normalizeTitleKey($title);
                $this->assertNotContains($key, $keys, $title);
                $keys[] = $key;
            }
        }
    }

    public function test_t7_non_numeric_skus_do_not_split_without_sku_name_labels(): void
    {
        $group = $this->group('game-topup');
        $names = [
            'Weekly Pass',
            'Monthly Membership',
            'Level Up Package',
            'Prime (1 Month)',
            'Weekly Deal Pack',
            'First Top-Up Bonus',
            'Elite Pack',
        ];
        $skus = [];
        foreach ($names as $i => $name) {
            $skus[] = ['name' => $name, 'price' => ($i + 1) * 10000];
        }
        $product = $this->product($group, 'topup-free-fire-id', 'Free Fire ID', $skus);
        $this->enable([$product->id]);
        $fresh = $product->fresh(['skus', 'catalogGroup']);
        $this->assertFalse($this->policy->decide($fresh)->split);
        $this->assertSame(TorobSplitDecision::REASON_UNKNOWN_MAPPING, $this->policy->decide($fresh)->reason);
        $this->assertCount(1, $this->mapper->toRows($fresh));
        $mixed = $this->product($group, 'topup-free-fire-ph', 'Free Fire PH', [
            ['name' => '100 Diamonds', 'price' => 100000],
            ['name' => 'Weekly Pass', 'price' => 200000],
        ]);
        $this->enable([$mixed->id]);
        $this->assertFalse($this->policy->decide($mixed->fresh(['skus', 'catalogGroup']))->split);
    }

    public function test_t7_unknown_region_remainder_does_not_split(): void
    {
        $group = $this->group('game-topup');
        foreach (['topup-free-fire-my-sg', 'topup-mobile-legends-exclusive', 'topup-mobile-legends-special'] as $handle) {
            $product = $this->product($group, $handle, $handle, [
                ['name' => '100 Diamonds', 'price' => 100000],
                ['name' => '310 Diamonds', 'price' => 200000],
            ]);
            $this->enable([$product->id]);
            $this->assertNull($this->copy->persianSkuTitle($product->fresh(['catalogGroup']), $product->skus->first()), $handle);
            $this->assertFalse($this->policy->decide($product->fresh(['skus', 'catalogGroup']))->split, $handle);
        }
    }

    public function test_t9_game_key_live_titles_get_unique_edition_suffix(): void
    {
        $group = $this->group('game-keys');
        $global = $this->product($group, 'gamekey-arc-raiders-global', 'ARC Raiders', [
            ['name' => 'Key', 'price' => 100000],
        ]);
        $cis = $this->product($group, 'gamekey-arc-raiders-cis', 'ARC Raiders', [
            ['name' => 'Key', 'price' => 100000],
        ]);
        $row = $this->product($group, 'gamekey-ghost-of-tsushima-directors-cut-row', "Ghost of Tsushima DIRECTOR'S CUT", [
            ['name' => 'Key', 'price' => 100000],
        ]);
        $this->assertSame('کلید بازی ARC Raiders نسخه گلوبال', $this->copy->torobTitle($global->fresh(['catalogGroup'])));
        $this->assertSame('کلید بازی ARC Raiders نسخه سی‌آی‌اس', $this->copy->torobTitle($cis->fresh(['catalogGroup'])));
        $this->assertSame("کلید بازی Ghost of Tsushima DIRECTOR'S CUT نسخه بین‌المللی", $this->copy->torobTitle($row->fresh(['catalogGroup'])));

        $catalog = [
            ['gamekey-doom-the-dark-ages-latam', 'DOOM: The Dark Ages', 'نسخه آمریکای لاتین'],
            ['gamekey-doom-the-dark-ages-mena', 'DOOM: The Dark Ages', 'نسخه خاورمیانه و شمال آفریقا'],
            ['gamekey-doom-the-dark-ages-asia', 'DOOM: The Dark Ages', 'نسخه آسیا'],
            ['gamekey-ghostwire-tokyo-latam', 'Ghostwire: Tokyo', 'نسخه آمریکای لاتین'],
            ['gamekey-ghostwire-tokyo-asia', 'Ghostwire: Tokyo', 'نسخه آسیا'],
            ['gamekey-ghostwire-tokyo-mena', 'Ghostwire: Tokyo', 'نسخه خاورمیانه و شمال آفریقا'],
            ['gamekey-heretic-hexen-mena', 'Heretic + Hexen', 'نسخه خاورمیانه و شمال آفریقا'],
            ['gamekey-heretic-hexen-latam', 'Heretic + Hexen', 'نسخه آمریکای لاتین'],
            ['gamekey-indiana-jones-great-circle-asia', 'Indiana Jones and the Great Circle', 'نسخه آسیا'],
            ['gamekey-indiana-jones-great-circle-latam', 'Indiana Jones and the Great Circle', 'نسخه آمریکای لاتین'],
            ['gamekey-prey-asia', 'Prey', 'نسخه آسیا'],
            ['gamekey-prey-latam', 'Prey', 'نسخه آمریکای لاتین'],
            ['gamekey-prey-mena', 'Prey', 'نسخه خاورمیانه و شمال آفریقا'],
            ['gamekey-elder-scrolls-4-oblivion-goty-latam', 'The Elder Scrolls IV: Oblivion GOTY', 'نسخه آمریکای لاتین'],
            ['gamekey-elder-scrolls-4-oblivion-goty-mena', 'The Elder Scrolls IV: Oblivion GOTY', 'نسخه خاورمیانه و شمال آفریقا'],
            ['gamekey-wolfenstein-old-blood-asia', 'Wolfenstein: The Old Blood', 'نسخه آسیا'],
            ['gamekey-wolfenstein-old-blood-mena', 'Wolfenstein: The Old Blood', 'نسخه خاورمیانه و شمال آفریقا'],
            ['gamekey-wolfenstein-old-blood-latam', 'Wolfenstein: The Old Blood', 'نسخه آمریکای لاتین'],
        ];
        $keys = [];
        foreach ($catalog as [$handle, $title, $suffix]) {
            $product = $this->product($group, $handle, $title, [
                ['name' => 'Key', 'price' => 100000],
            ]);
            $live = $this->copy->torobTitle($product->fresh(['catalogGroup']));
            $this->assertSame('کلید بازی '.$title.' '.$suffix, $live, $handle);
            $this->assertStringNotContainsString('هند', $live, $handle);
            $key = $this->copy->normalizeTitleKey($live);
            $this->assertNotContains($key, $keys, $handle.' '.$live);
            $keys[] = $key;
        }
        $this->assertCount(18, $keys);

        $uk = $this->product($group, 'gamekey-sample-edition-uk', 'Sample Edition', [
            ['name' => 'Key', 'price' => 100000],
        ]);
        $in = $this->product($group, 'gamekey-sample-edition-in', 'Sample Edition', [
            ['name' => 'Key', 'price' => 100000],
        ]);
        $this->assertSame('کلید بازی Sample Edition نسخه بریتانیا', $this->copy->torobTitle($uk->fresh(['catalogGroup'])));
        $this->assertSame('کلید بازی Sample Edition نسخه هند', $this->copy->torobTitle($in->fresh(['catalogGroup'])));
    }

    public function test_t10_live_unsplit_pubg_variant_titles_are_unique(): void
    {
        $group = $this->group('game-topup');
        $fast = $this->product($group, 'topup-pubg-mobile-fast', 'پابجی موبایل', [
            ['name' => '60 UC', 'price' => 100000],
        ]);
        $manual = $this->product($group, 'topup-pubg-mobile-manual', 'پابجی موبایل', [
            ['name' => '60 UC', 'price' => 100000],
        ]);
        $reserve = $this->product($group, 'topup-pubg-mobile-reserve', 'پابجی موبایل', [
            ['name' => '60 UC', 'price' => 100000],
        ]);
        $this->assertSame('شارژ بازی پابجی موبایل تحویل سریع', $this->copy->torobTitle($fast->fresh(['catalogGroup'])));
        $this->assertSame('شارژ بازی پابجی موبایل تحویل دستی', $this->copy->torobTitle($manual->fresh(['catalogGroup'])));
        $this->assertSame('شارژ بازی پابجی موبایل تحویل رزرو', $this->copy->torobTitle($reserve->fresh(['catalogGroup'])));
        $this->assertCount(3, array_unique([
            $this->copy->normalizeTitleKey($this->copy->torobTitle($fast->fresh(['catalogGroup']))),
            $this->copy->normalizeTitleKey($this->copy->torobTitle($manual->fresh(['catalogGroup']))),
            $this->copy->normalizeTitleKey($this->copy->torobTitle($reserve->fresh(['catalogGroup']))),
        ]));
    }

    public function test_cap_over_thirty_skus_and_no_orderable_sku_are_skipped(): void
    {
        $gift = $this->group('gift-card');
        $skus = [];
        for ($i = 1; $i <= 31; $i++) {
            $skus[] = ['name' => $i.' USD', 'offer' => $i.'_usd', 'price' => $i * 1000];
        }
        $over = $this->product($gift, 'giftcard-google-play-us', 'GP', $skus);
        $this->enable([$over->id]);
        $this->assertSame(TorobSplitDecision::REASON_OVER_MAX, $this->policy->decide($over->fresh(['skus', 'catalogGroup']))->reason);
        $this->assertSame(30, (int) config('torob.split_by_sku.max_items_per_product'));
        $this->assertSame(1.5, (float) config('torob.split_by_sku.max_feed_multiplier'));
        $this->assertSame(1500, (int) config('torob.split_by_sku.max_stage_extra_rows'));

        $hidden = $this->product($gift, 'giftcard-playstation-no', 'PS NO', [
            ['name' => '10 USD', 'offer' => '10_usd', 'price' => 100000],
            ['name' => '20 USD', 'offer' => '20_usd', 'price' => 200000],
        ], ['visible' => false, 'unavailable_at' => now()]);
        $this->enable([$hidden->id]);
        $this->assertSame(
            TorobSplitDecision::REASON_NO_ORDERABLE_SKU,
            $this->policy->decide($hidden->fresh(['skus', 'catalogGroup']))->reason,
        );
        $this->assertCount(1, $this->mapper->toRows($hidden->fresh(['skus', 'catalogGroup'])));
    }

    public function test_cambodia_and_sku_name_labels_default_empty(): void
    {
        $copy = require config_path('torob_split_copy.php');
        $this->assertSame('کامبوج', $copy['regions']['kh']);
        $this->assertSame([], $copy['sku_name_labels']);
        $this->assertSame('تحویل سریع', $copy['topup_variant_suffixes']['fast']);
        $this->assertSame('تحویل دستی', $copy['topup_variant_suffixes']['manual']);
        $this->assertSame('تحویل رزرو', $copy['topup_variant_suffixes']['reserve']);
        $this->assertSame('نسخه گلوبال', $copy['game_key_edition_labels']['global']);
        $this->assertSame('نسخه بین‌المللی', $copy['game_key_edition_labels']['row']);
        $this->assertSame('نسخه آمریکای لاتین', $copy['game_key_edition_labels']['latam']);
        $this->assertSame('نسخه خاورمیانه و شمال آفریقا', $copy['game_key_edition_labels']['mena']);
        $this->assertSame('نسخه آسیا', $copy['game_key_edition_labels']['asia']);
        $this->assertSame('نسخه بریتانیا', $copy['game_key_edition_labels']['uk']);
        $this->assertSame('نسخه هند', $copy['game_key_edition_labels']['in']);
        $gift = $this->group('gift-card');
        $product = $this->product($gift, 'giftcard-google-play-kh', 'GP KH', [
            ['name' => '10 USD', 'offer' => '10_usd', 'price' => 100000],
        ]);
        $this->assertSame(
            'گیفت کارت گوگل پلی کامبوج 10 دلار (KH)',
            $this->copy->persianSkuTitle($product->fresh(['catalogGroup']), $product->skus->first()),
        );
    }

    /**
     * @param  list<int>  $ids
     */
    private function enable(array $ids): void
    {
        config([
            'torob.split_by_sku.enabled_product_ids' => $ids,
            'torob.split_by_sku_product_ids' => $ids,
        ]);
        $this->mapper = $this->app->make(TorobProductMapper::class);
        $this->policy = $this->app->make(TorobSplitPolicy::class);
        $this->copy = $this->app->make(TorobFeedCatalogCopy::class);
    }

    /**
     * @return list<string>
     */
    private function titles(Product $product): array
    {
        $rows = $this->mapper->toRows($product->fresh(['skus', 'catalogGroup']));

        return array_values(array_map(static fn (array $row): string => (string) $row['title'], $rows));
    }

    private function group(string $slug): CatalogGroup
    {
        $names = [
            'gift-card' => 'گیفت کارت',
            'game-topup' => 'شارژ بازی',
            'game-keys' => 'کلید بازی',
            'telegram' => 'تلگرام',
        ];

        return CatalogGroup::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $names[$slug] ?? $slug, 'title' => $names[$slug] ?? $slug, 'visible' => true, 'sort_order' => 10],
        );
    }

    /**
     * @param  list<array{name: string, price: int, offer?: string}>  $skus
     * @param  array<string, mixed>  $extra
     */
    private function product(CatalogGroup $group, string $handle, string $title, array $skus, array $extra = []): Product
    {
        $product = Product::query()->create(array_merge([
            'catalog_group_id' => $group->id,
            'handle' => $handle,
            'code' => $handle,
            'title' => $title,
            'publisher' => '',
            'subtitle' => '',
            'thumbnail' => '',
            'category_name' => $group->name,
            'visible' => true,
            'status' => 'active',
            'indexable' => true,
            'sort_order' => 10,
            'inputs' => [],
        ], $extra));
        foreach ($skus as $index => $sku) {
            Sku::query()->create([
                'product_id' => $product->id,
                'code' => $handle.'-'.$index,
                'name' => $sku['name'],
                'price' => $sku['price'],
                'status' => 'active',
                'visible' => true,
                'sort_order' => $index + 1,
                'fazercards_offer_id' => $sku['offer'] ?? null,
            ]);
        }

        return $product->fresh(['skus', 'catalogGroup']);
    }
}
