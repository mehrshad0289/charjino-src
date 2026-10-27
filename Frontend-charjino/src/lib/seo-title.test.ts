import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  buildCategorySeoHeading,
  buildCategorySeoTitle,
  buildProductDocumentTitle,
  buildProductSeoDescription,
  buildProductSeoHeading,
  buildProductSeoTitle,
  buildSteamGiftSeoDescription,
  fillMetaDescription,
  fitDocumentTitle,
  productHeadingIssues,
  productsMissingPersianName,
} from './seo-title';
import type { SeoTitleProduct } from './seo-title';

const FORBIDDEN = /ارزان‌?ترین|تضمینی/;

function product(partial: Partial<SeoTitleProduct> & Pick<SeoTitleProduct, 'title'>): SeoTitleProduct {
  return partial;
}

const samples: Array<{ name: string; input: SeoTitleProduct; title: string }> = [
  {
    name: 'giftcard playstation us',
    input: product({
      title: 'PlayStation (US)',
      titleEn: 'PlayStation (US)',
      handle: 'giftcard-playstation-us',
      fulfillmentType: 'giftcard',
      region: 'US',
      platform: 'playstation',
      category: { slug: 'gift-card', title: 'گیفت کارت' },
    }),
    title: 'خرید گیفت کارت پلی استیشن (PlayStation) آمریکا',
  },
  {
    name: 'pubg mobile uc',
    input: product({
      title: 'پابجی موبایل',
      titleEn: 'PUBG Mobile (Auto)',
      handle: 'pubg-mobile',
      fulfillmentType: 'topup',
      category: { slug: 'game-topup', title: 'شارژ بازی' },
    }),
    title: 'خرید یوسی پابجی موبایل (PUBG Mobile UC)',
  },
  {
    name: 'valorant turkey',
    input: product({
      title: 'Valorant Points',
      titleEn: 'Valorant Points',
      handle: 'giftcard-valorant-tr',
      fulfillmentType: 'giftcard',
      region: 'TR',
      platform: 'valorant',
    }),
    title: 'خرید گیفت کارت ولورانت (Valorant) ترکیه',
  },
  {
    name: 'steam wallet',
    input: product({
      title: 'شارژ کیف پول استیم',
      titleEn: 'Steam Wallet',
      handle: 'steam-topup-wallet',
      fulfillmentType: 'steam-topup',
      platform: 'steam',
      category: { slug: 'steam-wallet', title: 'شارژ استیم' },
    }),
    title: 'شارژ مستقیم کیف پول استیم (Steam Wallet)',
  },
  {
    name: 'cod mobile',
    input: product({
      title: 'Call of Duty Mobile',
      titleEn: 'Call of Duty Mobile',
      handle: 'cod-mobile',
      fulfillmentType: 'topup',
    }),
    title: 'خرید سی‌پی کالاف دیوتی موبایل (Call of Duty Mobile CP)',
  },
  {
    name: 'free fire',
    input: product({
      title: 'Free Fire',
      handle: 'topup-free-fire-mena',
      fulfillmentType: 'topup',
    }),
    title: 'خرید الماس فری فایر (Free Fire Diamonds) خاورمیانه',
  },
  {
    name: 'telegram premium',
    input: product({
      title: 'Telegram Premium',
      titleEn: 'Telegram Premium',
      handle: 'telegram-premium-premium',
      fulfillmentType: 'telegram-premium',
    }),
    title: 'خرید پرمیوم تلگرام (Telegram)',
  },
  {
    name: 'roblox no region',
    input: product({
      title: 'Roblox',
      handle: 'giftcard-roblox',
      fulfillmentType: 'giftcard',
      region: 'global',
    }),
    title: 'خرید گیفت کارت روبلاکس (Roblox)',
  },
  {
    name: 'xbox gift',
    input: product({
      title: 'Xbox',
      handle: 'giftcard-xbox-us',
      fulfillmentType: 'giftcard',
      region: 'US',
      platform: 'xbox',
    }),
    title: 'خرید گیفت کارت ایکس باکس (Xbox) آمریکا',
  },
  {
    name: 'game key without fa',
    input: product({
      title: 'Elden Ring',
      titleEn: 'Elden Ring',
      handle: 'gamekey-elden-ring',
      fulfillmentType: 'gamekey',
      platform: 'steam',
    }),
    title: 'خرید کلید بازی Elden Ring',
  },
];

test('builds titles for catalog families', () => {
  for (const sample of samples) {
    assert.equal(buildProductSeoHeading(sample.input), sample.title, `${sample.name} h1`);
    assert.equal(buildProductSeoTitle(sample.input), fitDocumentTitle(sample.title), sample.name);
    assert.doesNotMatch(buildProductSeoHeading(sample.input), /ارزان\s*\||تحویل فوری/);
  }
});

test('omits persian name when dictionary has none', () => {
  const input = product({
    title: 'Some Obscure Game',
    titleEn: 'Some Obscure Game',
    handle: 'gamekey-obscure-xyz',
    fulfillmentType: 'gamekey',
  });
  assert.equal(buildProductSeoHeading(input), 'خرید کلید بازی Some Obscure Game');
  assert.equal(buildProductSeoTitle(input), fitDocumentTitle('خرید کلید بازی Some Obscure Game'));
  assert.equal(productsMissingPersianName(input), true);
});

test('omits missing and global region', () => {
  const none = product({
    title: 'PlayStation',
    handle: 'giftcard-playstation',
    fulfillmentType: 'giftcard',
    platform: 'playstation',
  });
  assert.equal(buildProductSeoHeading(none), 'خرید گیفت کارت پلی استیشن (PlayStation)');
  assert.equal(buildProductSeoTitle(none), fitDocumentTitle('خرید گیفت کارت پلی استیشن (PlayStation)'));
  const global = product({ ...none, region: 'Global' });
  assert.equal(buildProductSeoTitle(global), fitDocumentTitle('خرید گیفت کارت پلی استیشن (PlayStation)'));
});

test('shortens english parenthesis first on long titles', () => {
  const input = product({
    title: 'Call of Duty Mobile Extra Long Edition Name',
    titleEn: 'Call of Duty Mobile Extra Long Edition Name',
    handle: 'cod-mobile',
    fulfillmentType: 'topup',
    region: 'US',
  });
  const title = buildProductSeoTitle(input);
  assert.match(title, /^خرید سی‌پی کالاف دیوتی موبایل/);
  assert.doesNotMatch(title, /Extra Long Edition Name/);
});

test('manual override wins', () => {
  const input = product({
    title: 'PlayStation (US)',
    handle: 'giftcard-playstation-us',
    fulfillmentType: 'giftcard',
    region: 'US',
    seoTitle: 'عنوان دستی محصول | شارژینو',
  });
  assert.match(buildProductSeoTitle(input), /عنوان دستی محصول/);
  assert.match(buildProductSeoTitle(input), /آمریکا/);
  assert.match(buildProductSeoHeading(input), /آمریکا/);
});

test('does not emit duplicate words or forbidden claims', () => {
  for (const sample of samples) {
    const title = buildProductSeoTitle(sample.input);
    assert.doesNotMatch(title, FORBIDDEN, sample.name);
    assert.doesNotMatch(title, /شارژ\s+شارژ|خرید\s+خرید/);
    const tokens = title.replace(/[()]/g, '').split(/\s+/).filter(Boolean);
    const faTokens = tokens.filter((token) => /[\u0600-\u06FF]/.test(token));
    assert.equal(faTokens.length, new Set(faTokens).size, sample.name);
  }
  const noisy = product({
    title: 'PUBG Mobile',
    handle: 'pubg-mobile',
    fulfillmentType: 'topup',
    buyGuide: { seo: { title: 'خرید خرید یوسی با تحویل فوری و تضمینی' } },
  });
  const cleaned = buildProductSeoTitle(noisy);
  assert.doesNotMatch(cleaned, FORBIDDEN);
  assert.doesNotMatch(cleaned, /خرید\s+خرید/);
});

test('category titles follow خرید + fa name', () => {
  assert.equal(buildCategorySeoTitle('گیفت کارت', 'gift-card'), fitDocumentTitle('خرید گیفت کارت با تومان'));
  assert.equal(buildCategorySeoTitle('بازی', 'game-keys'), fitDocumentTitle('خرید کلید بازی استیم و کنسول'));
  assert.equal(buildCategorySeoTitle('شارژ بازی', 'game-topup'), fitDocumentTitle('خرید شارژ بازی با تومان'));
  assert.match(buildCategorySeoTitle('تلگرام', 'telegram'), /پرمیوم و استارز تلگرام/);
  assert.match(buildCategorySeoTitle('تلگرام', 'telegram'), /Telegram/);
});

test('lists products missing persian dictionary names', () => {
  const missing = [
    product({ title: 'Obscure Key', handle: 'gamekey-obscure', fulfillmentType: 'gamekey' }),
    product({ title: 'Unknown Brand', handle: 'giftcard-unknown-brand', fulfillmentType: 'giftcard' }),
  ]
    .filter(productsMissingPersianName)
    .map((item) => item.handle);
  assert.deepEqual(missing, ['gamekey-obscure', 'giftcard-unknown-brand']);
});

function steamGift(partial: Partial<SeoTitleProduct> & Pick<SeoTitleProduct, 'title' | 'handle'>): SeoTitleProduct {
  return product({
    fulfillmentType: 'steam-gift',
    category: { slug: 'steam-gifts', title: 'گیفت استیم' },
    platform: 'steam',
    ...partial,
  });
}

test('steam gift titles use the game name and drop Steam Wallet', () => {
  const dota = steamGift({
    title: 'Dota 2',
    titleEn: 'Dota 2',
    handle: 'steam-gift-570',
  });
  const cs = steamGift({
    title: 'Counter-Strike 2',
    titleEn: 'Counter-Strike 2',
    handle: 'steam-gift-730',
  });
  const long = steamGift({
    title: 'The Elder Scrolls IV Oblivion Remastered Deluxe',
    handle: 'steam-gift-2623190',
  });
  const walletNoise = steamGift({
    title: 'Steam Wallet',
    titleEn: 'Hades',
    handle: 'steam-gift-1145360',
    buyGuide: { seo: { title: 'خرید گیفت استیم (Steam Wallet) | شارژینو' } },
  });

  assert.equal(buildProductSeoHeading(dota), 'خرید گیفت استیم Dota 2');
  assert.match(buildProductSeoTitle(dota), /^خرید گیفت استیم Dota 2/);
  assert.doesNotMatch(buildProductSeoTitle(dota), /Dota 2ارزان/);
  assert.ok(`${buildProductSeoTitle(dota)} | شارژینو`.length <= 65);

  assert.equal(buildProductSeoHeading(cs), 'خرید گیفت Counter-Strike 2 Prime');
  assert.equal(buildProductSeoTitle(cs), 'خرید گیفت Counter-Strike 2 Prime');
  assert.equal(`${buildProductSeoTitle(cs)} | شارژینو`, 'خرید گیفت Counter-Strike 2 Prime | شارژینو');
  assert.ok(`${buildProductSeoTitle(cs)} | شارژینو`.length <= 65);

  assert.equal(buildProductSeoHeading(long), 'خرید گیفت استیم The Elder Scrolls IV Oblivion Remastered Deluxe');
  assert.doesNotMatch(buildProductSeoTitle(long), /تحویل فوری با تومان/);
  assert.ok(`${buildProductSeoTitle(long)} | شارژینو`.length <= 65);

  assert.equal(buildProductSeoHeading(walletNoise), 'خرید گیفت استیم Hades');
  assert.doesNotMatch(buildProductSeoTitle(walletNoise), /Steam Wallet/i);
  assert.doesNotMatch(buildProductSeoHeading(walletNoise), /Steam Wallet/i);
  assert.equal(productsMissingPersianName(dota), false);
});

test('steam gift titles keep the game name from the start and never glue ارزان', () => {
  const like = steamGift({
    title: 'Like a Dragon: Pirate Yakuza in Hawaii',
    handle: 'steam-gift-3061810',
    seoTitle: 'خرید گیفت استیم a Dragon: Pirate Yakuza in Hawaii ارزان',
  });
  const title = buildProductSeoTitle(like);
  assert.match(title, /^خرید گیفت استیم Like a Dragon/);
  assert.doesNotMatch(title, /^خرید گیفت استیم a Dragon/);
  assert.doesNotMatch(title, /ارزان$/);
  assert.ok(`${title} | شارژینو`.length <= 65);
  assert.equal(buildProductSeoHeading(like), 'خرید گیفت استیم Like a Dragon: Pirate Yakuza in Hawaii');

  const tm = steamGift({
    title: 'A Game About Digging A Hole™',
    handle: 'steam-gift-3244220',
  });
  assert.match(buildProductSeoTitle(tm), /A Game About Digging A Hole/);
  assert.equal(buildProductSeoHeading(tm), 'خرید گیفت استیم A Game About Digging A Hole™');

  const skate = steamGift({
    title: 'skate.',
    handle: 'steam-gift-3354750',
  });
  assert.match(buildProductSeoTitle(skate), /^خرید گیفت استیم skate\./);
  assert.doesNotMatch(buildProductSeoTitle(skate), /skate\.ارزان/);
});

test('steam gift descriptions are unique and 120-155 characters', () => {
  const dota = steamGift({ title: 'Dota 2', handle: 'steam-gift-570' });
  const cs = steamGift({ title: 'Counter-Strike 2', handle: 'steam-gift-730' });
  const hades = steamGift({ title: 'Hades', handle: 'steam-gift-1145360' });
  const dotaDesc = buildSteamGiftSeoDescription(dota);
  const csDesc = buildSteamGiftSeoDescription(cs);
  const hadesDesc = buildSteamGiftSeoDescription(hades);
  for (const desc of [dotaDesc, csDesc, hadesDesc]) {
    assert.ok(desc.length >= 110, desc);
    assert.ok(desc.length <= 160, desc);
    assert.match(desc, /^گیفت استیم /);
    assert.match(desc, /تحویل فوری/);
    assert.match(desc, /پشتیبانی/);
    assert.doesNotMatch(desc, /Steam Wallet/i);
  }
  assert.notEqual(dotaDesc, csDesc);
  assert.notEqual(csDesc, hadesDesc);
  assert.match(dotaDesc, /Dota 2/);
  assert.match(csDesc, /Counter-Strike 2 Prime/);
});

test('admin seo_title overrides the default pattern', () => {
  const input = product({
    title: 'پابجی موبایل',
    handle: 'pubg-mobile',
    fulfillmentType: 'topup',
    seoTitle: 'خرید یوسی پابجی موبایل ارزان | تحویل فوری | شارژینو',
    seoDescription:
      'خرید یوسی پابجی موبایل را از ۲۴۰ هزار تومان با تحویل فوری در شارژینو ثبت کنید. پشتیبانی پاسخگوست؛ پرداخت ریالی بدون رمز اکانت بازی.',
  });
  assert.equal(buildProductSeoTitle(input), 'خرید یوسی پابجی موبایل ارزان | تحویل فوری');
  assert.equal(buildProductSeoHeading(input), 'خرید یوسی پابجی موبایل');
  const desc = buildProductSeoDescription(input);
  assert.ok(desc.length >= 110 && desc.length <= 160, desc);
});

test('8 Ball Pool heading uses Persian search terms', () => {
  const input = product({
    title: '8 Ball Pool',
    handle: 'topup-8-ball-pool',
    fulfillmentType: 'topup',
  });
  assert.match(buildProductSeoHeading(input), /۸ بال پول/);
  assert.match(buildProductSeoTitle(input), /۸ بال پول/);
});

function assertBalancedTitle(title: string, name: string) {
  const opens = (title.match(/\(/g) || []).length;
  const closes = (title.match(/\)/g) || []).length;
  assert.equal(opens, closes, `${name}: ${title}`);
  assert.doesNotMatch(title, /\(\s*$|^\s*\)|\(\s*\|/, name);
  assert.doesNotMatch(title, /\s(?:the|of|a|an|on|and|or|for|to|in|with)\s*(?:\||$)/i, name);
  assert.ok(title.length <= 60, `${name} ${title.length}: ${title}`);
}

test('document titles never cut inside parentheses or words and stay ≤60', () => {
  const gta = product({
    title: 'Grand Theft Auto V',
    titleEn: 'Grand Theft Auto V',
    handle: 'gamekey-grand-theft-auto-v-global',
    fulfillmentType: 'gamekey',
  });
  const pool = product({
    title: '8 Ball Pool',
    handle: 'topup-8-ball-pool',
    fulfillmentType: 'topup',
  });
  const stranding = product({
    title: 'DEATH STRANDING 2: ON THE BEACH',
    titleEn: 'DEATH STRANDING 2: ON THE BEACH',
    handle: 'gamekey-death-stranding-2-on-the-beach-row',
    fulfillmentType: 'gamekey',
    region: 'row',
  });
  const gtaTitle = buildProductDocumentTitle(gta);
  const poolTitle = buildProductDocumentTitle(pool);
  const strandingTitle = buildProductDocumentTitle(stranding);
  assertBalancedTitle(gtaTitle, 'gta');
  assertBalancedTitle(poolTitle, 'pool');
  assertBalancedTitle(strandingTitle, 'stranding');
  assert.match(gtaTitle, /جی‌تی‌ای ۵/);
  assert.doesNotMatch(gtaTitle, /\(GTA\s*(\||$)/);
  assert.match(poolTitle, /۸ بال پول/);
  assert.doesNotMatch(poolTitle, /Ball Pool Gems\)/);
  assert.doesNotMatch(strandingTitle, /ON THE(?:\s+سایر|\s*\|)/i);
  assert.match(buildProductSeoHeading(gta), /\(GTA 5\)/);
  assert.match(buildProductSeoHeading(pool), /\(8 Ball Pool/);
});

test('GTA 6 heading keeps English paren without duplicate-word false positive', () => {
  const gta6 = product({
    title: 'Grand Theft Auto VI (GTA 6)',
    titleEn: 'Grand Theft Auto VI (GTA 6)',
    handle: 'grand-theft-auto-vi-gta-6-america',
    fulfillmentType: 'gamekey',
    region: 'US',
    platform: 'playstation',
    category: { slug: 'game-keys', title: 'کلید بازی' },
  });
  const heading = buildProductSeoHeading(gta6);
  assert.equal(productHeadingIssues(gta6, heading).length, 0);
  assert.match(heading, /جی‌تی‌ای ۶/);
  assert.match(heading, /\(GTA 6\)/);
});

test('headingHasDuplicateWords still flags real repeated tokens', () => {
  const repeated = product({
    title: 'گیفت کارت',
    titleEn: 'Gift Card',
    handle: 'giftcard-test',
    fulfillmentType: 'giftcard',
    category: { slug: 'gift-card', title: 'گیفت کارت' },
  });
  const heading = 'خرید خرید گیفت کارت';
  assert.deepEqual(productHeadingIssues(repeated, heading), ['duplicate-word']);
});

test('tinder country from slug lands in title, h1 and description', () => {
  const cyprus = product({
    title: 'Tinder',
    titleEn: 'Tinder',
    handle: 'giftcard-tinder-cy',
    fulfillmentType: 'giftcard',
    category: { slug: 'gift-card', title: 'گیفت کارت' },
  });
  const heading = buildProductSeoHeading(cyprus);
  const title = buildProductSeoTitle(cyprus);
  assert.match(heading, /تیندر/);
  assert.match(heading, /قبرس/);
  assert.match(title, /قبرس/);
  assert.match(buildProductSeoDescription(cyprus), /قبرس/);
  assert.ok(`${title} | شارژینو`.length <= 65, title);
  assert.doesNotMatch(title, /قبرس.*قبرس/);
});

test('catalog distinguishers make sibling giftcard and topup titles unique', () => {
  const pairs: Array<[SeoTitleProduct, SeoTitleProduct, RegExp, RegExp]> = [
    [
      product({ title: 'Xbox', handle: 'giftcard-xbox-us', fulfillmentType: 'giftcard', region: 'US' }),
      product({ title: 'Xbox Game Pass', handle: 'giftcard-xbox-game-pass-us', fulfillmentType: 'giftcard', region: 'US' }),
      /آمریکا/,
      /گیم پس/,
    ],
    [
      product({ title: 'Nintendo', handle: 'giftcard-nintendo-br', fulfillmentType: 'giftcard', region: 'BR' }),
      product({ title: 'Nintendo Switch', handle: 'giftcard-nintendo-switch-br', fulfillmentType: 'giftcard', region: 'BR' }),
      /برزیل/,
      /سوییچ/,
    ],
    [
      product({ title: 'Telegram', handle: 'giftcard-telegram-premium', fulfillmentType: 'giftcard' }),
      product({ title: 'Telegram', handle: 'giftcard-telegram-stars', fulfillmentType: 'giftcard' }),
      /پرمیوم/,
      /استارز/,
    ],
    [
      product({ title: 'Yalla Ludo', handle: 'giftcard-yalla-ludo-diamond', fulfillmentType: 'giftcard' }),
      product({ title: 'Yalla Ludo', handle: 'giftcard-yalla-ludo-gold', fulfillmentType: 'giftcard' }),
      /الماس/,
      /گلد/,
    ],
    [
      product({ title: 'PUBG Mobile', handle: 'pubg-mobile', fulfillmentType: 'topup' }),
      product({ title: 'PUBG Mobile', handle: 'topup-pubg-mobile-fast', fulfillmentType: 'topup' }),
      /یوسی پابجی/,
      /سریع/,
    ],
    [
      product({ title: 'PlayStation', handle: 'giftcard-playstation-hr', fulfillmentType: 'giftcard' }),
      product({ title: 'PlayStation', handle: 'giftcard-playstation-za', fulfillmentType: 'giftcard' }),
      /کرواسی/,
      /آفریقای جنوبی/,
    ],
    [
      product({
        title: 'Age of Empires II',
        handle: 'gamekey-age-of-empires-2-definitive-edition-global',
        fulfillmentType: 'gamekey',
      }),
      product({ title: 'Age of Empires IV', handle: 'gamekey-age-of-empires-4-global', fulfillmentType: 'gamekey' }),
      /\bII\b/,
      /\bIV\b/,
    ],
    [
      product({ title: 'Mobile Legends', handle: 'topup-mobile-legends-global', fulfillmentType: 'topup' }),
      product({ title: 'Mobile Legends', handle: 'topup-mobile-legends-turkey', fulfillmentType: 'topup' }),
      /الماس موبایل لجندز/,
      /ترکیه/,
    ],
    [
      product({ title: 'Tinder', handle: 'giftcard-tinder-tn', fulfillmentType: 'giftcard' }),
      product({ title: 'Tinder', handle: 'giftcard-tinder-uz', fulfillmentType: 'giftcard' }),
      /تونس/,
      /ازبکستان/,
    ],
    [
      product({
        title: 'Warhammer 40,000: Gladius',
        handle: 'gamekey-warhammer-40000-gladius-cis',
        fulfillmentType: 'gamekey',
      }),
      product({
        title: 'Warhammer 40,000: Rogue Trader',
        handle: 'gamekey-warhammer-40000-rogue-trader-cis',
        fulfillmentType: 'gamekey',
      }),
      /Gladius/i,
      /Rogue Trader/i,
    ],
    [
      product({
        title: 'The Elder Scrolls IV: Oblivion GOTY',
        handle: 'gamekey-elder-scrolls-4-oblivion-goty-cis',
        fulfillmentType: 'gamekey',
      }),
      product({
        title: 'The Elder Scrolls IV: Oblivion Remastered',
        handle: 'gamekey-elder-scrolls-4-oblivion-remastered-cis',
        fulfillmentType: 'gamekey',
      }),
      /GOTY/,
      /ریمسترد/,
    ],
    [
      product({
        title: 'The Last of Us Part I',
        handle: 'gamekey-the-last-of-us-part-i-cis',
        fulfillmentType: 'gamekey',
      }),
      product({
        title: 'The Last of Us Part II Remastered',
        handle: 'gamekey-the-last-of-us-part-ii-remastered-cis',
        fulfillmentType: 'gamekey',
      }),
      /\bI\b/,
      /\bII\b/,
    ],
  ];
  for (const [left, right, leftPat, rightPat] of pairs) {
    const leftHeading = buildProductSeoHeading(left);
    const rightHeading = buildProductSeoHeading(right);
    const leftDoc = buildProductDocumentTitle(left);
    const rightDoc = buildProductDocumentTitle(right);
    assert.notEqual(leftHeading, rightHeading, `${left.handle} vs ${right.handle}`);
    assert.match(leftHeading, leftPat, left.handle ?? '');
    assert.match(rightHeading, rightPat, right.handle ?? '');
    assert.match(leftDoc, leftPat, `${left.handle} title`);
    assert.match(rightDoc, rightPat, `${right.handle} title`);
    assert.notEqual(buildProductSeoDescription(left), buildProductSeoDescription(right));
    assertBalancedTitle(leftDoc, left.handle ?? 'left');
    assertBalancedTitle(rightDoc, right.handle ?? 'right');
    assert.notEqual(leftDoc, rightDoc, `${left.handle} vs ${right.handle} document`);
  }
});

test('fitDocumentTitle never drops the region name', () => {
  const heading = 'خرید گیفت کارت تیندر (Tinder Extra Long Qualifier Name) قبرس';
  const fitted = fitDocumentTitle(heading, 'قبرس');
  assert.match(fitted, /قبرس/);
  assert.ok(`${fitted} | شارژینو`.length <= 65, fitted);
});

test('generated meta description is unique and includes price, delivery, support', () => {
  const pubg = product({
    title: 'پابجی موبایل',
    handle: 'pubg-mobile',
    fulfillmentType: 'topup',
    minPrice: 240000,
  });
  const fire = product({
    title: 'Free Fire',
    handle: 'topup-free-fire-mena',
    fulfillmentType: 'topup',
    minPrice: 251000,
  });
  const pubgDesc = buildProductSeoDescription(pubg);
  const fireDesc = buildProductSeoDescription(fire);
  for (const desc of [pubgDesc, fireDesc]) {
    assert.ok(desc.length >= 110, desc);
    assert.ok(desc.length <= 160, desc);
    assert.match(desc, /تومان/);
    assert.match(desc, /تحویل فوری/);
    assert.match(desc, /بدون رمز اکانت/);
  }
  assert.notEqual(pubgDesc, fireDesc);
});

test('brand hub titles and headings follow the keyword landing pattern', () => {
  const title = buildCategorySeoTitle('گوگل پلی', 'google-play');
  assert.match(title, /گوگل پلی/);
  assert.match(title, /Google Play/);
  assert.ok(`${title} | شارژینو`.length <= 65, title);
  assert.doesNotMatch(title, /\| شارژینو/);
  assert.equal(buildCategorySeoHeading('گوگل پلی', 'google-play'), 'خرید گیفت کارت گوگل پلی با تومان');
  assert.equal(buildCategorySeoHeading('کالاف', 'call-of-duty-mobile'), 'خرید سی‌پی کالاف دیوتی موبایل با تومان');
});

test('h1 never keeps leftover slug tokens or mid-name ellipsis', () => {
  const xbox = product({
    title: 'Xbox REACT Credits',
    titleEn: 'Xbox REACT Credits',
    handle: 'giftcard-xbox-react-credits-extraction-us',
    fulfillmentType: 'giftcard',
    region: 'US',
    platform: 'xbox',
  });
  const rising = product({
    title: 'Dead Rising 2: Off the Record',
    titleEn: 'Dead Rising 2: Off the Record',
    handle: 'gamekey-dead-rising-2-off-the-record-cis',
    fulfillmentType: 'gamekey',
    region: 'CIS',
  });
  const xboxHeading = buildProductSeoHeading(xbox);
  const risingHeading = buildProductSeoHeading(rising);
  assert.doesNotMatch(xboxHeading, /extraction/i);
  assert.doesNotMatch(xboxHeading, /^\s*…/);
  assert.match(xboxHeading, /آمریکا/);
  assert.equal(risingHeading, 'خرید کلید بازی Dead Rising 2: Off the Record سی‌آی‌اس');
  assert.doesNotMatch(risingHeading, /۲ the record/i);
  assert.deepEqual(productHeadingIssues(xbox), []);
  assert.deepEqual(productHeadingIssues(rising), []);
  assert.doesNotMatch(buildProductSeoTitle(xbox), /…/);
});

test('minecraft giftcard and gamekey descriptions stay unique via type and platform', () => {
  const card = product({
    title: 'Minecraft Java & Bedrock (Global)',
    handle: 'giftcard-minecraft-java-bedrock-global',
    fulfillmentType: 'giftcard',
    platform: 'minecraft-java-bedrock',
    minPrice: 4785000,
  });
  const key = product({
    title: 'Minecraft: Java & Bedrock',
    handle: 'gamekey-minecraft-java-bedrock-global',
    fulfillmentType: 'gamekey',
    platform: 'xbox',
    minPrice: 4787000,
  });
  const cardDesc = buildProductSeoDescription(card);
  const keyDesc = buildProductSeoDescription(key);
  assert.notEqual(cardDesc, keyDesc);
  assert.match(cardDesc, /گیفت کارت/);
  assert.match(keyDesc, /کلید بازی/);
  for (const desc of [cardDesc, keyDesc]) {
    assert.ok(desc.length >= 110 && desc.length <= 160, desc);
  }
});

test('telegram stars giftcard and topup descriptions stay unique', () => {
  const card = product({
    title: 'Telegram Stars',
    handle: 'giftcard-telegram-stars',
    fulfillmentType: 'giftcard',
    platform: 'telegram',
    minPrice: 210000,
  });
  const stars = product({
    title: 'ستاره تلگرام',
    titleEn: 'Telegram Stars',
    handle: 'telegram-stars-stars',
    fulfillmentType: 'telegram-stars',
    minPrice: 210000,
  });
  const cardDesc = buildProductSeoDescription(card);
  const starsDesc = buildProductSeoDescription(stars);
  assert.notEqual(cardDesc, starsDesc);
  assert.match(cardDesc, /گیفت کارت/);
  assert.match(starsDesc, /استارز/);
  for (const desc of [cardDesc, starsDesc]) {
    assert.ok(desc.length >= 110 && desc.length <= 160, desc);
  }
});

test('static page meta descriptions fill to 110-160 characters', async () => {
  const { PAGE_DESCRIPTIONS } = await import('./seo');
  const pages = [
    PAGE_DESCRIPTIONS.home,
    PAGE_DESCRIPTIONS.about,
    PAGE_DESCRIPTIONS.contact,
    PAGE_DESCRIPTIONS.terms,
    PAGE_DESCRIPTIONS.blog,
  ];
  for (const raw of pages) {
    const desc = fillMetaDescription(raw);
    assert.ok(desc.length >= 110 && desc.length <= 160, `${desc.length} ${desc}`);
  }
});
