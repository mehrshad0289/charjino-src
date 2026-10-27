import { test } from 'node:test';
import assert from 'node:assert/strict';
import { jsonLdBrandName, productJsonLd } from './json-ld';
import type { CatalogProduct, CatalogSku } from '@/types/catalog';

function product(partial: Partial<CatalogProduct> & Pick<CatalogProduct, 'handle' | 'title'>): CatalogProduct {
  return {
    isOrderable: true,
    ...partial,
  } as CatalogProduct;
}

function offerOf(data: Record<string, unknown>): Record<string, unknown> {
  const offers = data.offers;
  assert.ok(offers && typeof offers === 'object');
  return offers as Record<string, unknown>;
}

function offersOf(data: Record<string, unknown>): Record<string, unknown>[] {
  const offers = data.offers;
  assert.ok(Array.isArray(offers));
  return offers as Record<string, unknown>[];
}

test('steam gift JSON-LD uses Steam brand, video-game GPC, digital shipping, and no-return policy', () => {
  const data = productJsonLd(
    product({
      handle: 'steam-gift-730',
      title: 'Counter-Strike 2',
      titleEn: 'Counter-Strike 2',
      fulfillmentType: 'steam-gift',
      publisher: '',
      minPrice: 185000,
      category: { slug: 'steam-gifts', title: 'گیفت استیم' },
      comments_count: 0,
    }),
  );
  assert.deepEqual(data.brand, { '@type': 'Brand', name: 'Steam' });
  assert.deepEqual(data.category, {
    '@type': 'CategoryCode',
    inCodeSet: 'https://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.txt',
    codeValue: '1279',
  });
  const offer = offerOf(data);
  assert.equal(offer['@type'], 'Offer');
  assert.equal(offer.priceCurrency, 'IRR');
  assert.equal(offer.price, 1850000);
  assert.equal(offer.availability, 'https://schema.org/InStock');
  assert.equal(String(offer.url).endsWith('/catalog/steam-gift-730'), true);
  assert.deepEqual(offer.shippingDetails, {
    '@type': 'OfferShippingDetails',
    shippingRate: { '@type': 'MonetaryAmount', value: 0, currency: 'IRR' },
    deliveryTime: {
      '@type': 'ShippingDeliveryTime',
      handlingTime: { '@type': 'QuantitativeValue', minValue: 0, maxValue: 0, unitCode: 'DAY' },
      transitTime: { '@type': 'QuantitativeValue', minValue: 0, maxValue: 0, unitCode: 'DAY' },
    },
    shippingDestination: { '@type': 'DefinedRegion', addressCountry: 'IR' },
  });
  assert.equal((offer.hasMerchantReturnPolicy as { returnPolicyCategory: string }).returnPolicyCategory, 'https://schema.org/MerchantReturnNotPermitted');
  assert.equal((offer.hasMerchantReturnPolicy as { applicableCountry: string }).applicableCountry, 'IR');
  assert.equal('aggregateRating' in data, false);
  assert.equal('review' in data, false);
});

test('topup product JSON-LD uses real game brand and virtual-currency GPC', () => {
  const data = productJsonLd(
    product({
      handle: 'pubg-mobile',
      title: 'پابجی موبایل',
      titleEn: 'PUBG Mobile',
      fulfillmentType: 'topup',
      minPrice: 42000,
      category: { slug: 'game-topup', title: 'شارژ بازی' },
    }),
  );
  assert.deepEqual(data.brand, { '@type': 'Brand', name: 'PUBG Mobile' });
  assert.equal((data.category as { codeValue: string }).codeValue, '5935');
  const offer = offerOf(data);
  assert.equal(offer['@type'], 'Offer');
  assert.equal(offer.priceCurrency, 'IRR');
  assert.equal(offer.price, 420000);
  assert.ok(offer.shippingDetails);
  assert.ok(offer.hasMerchantReturnPolicy);
});

test('Free Fire JSON-LD brand is Garena Free Fire', () => {
  const data = productJsonLd(
    product({
      handle: 'topup-free-fire-mena',
      title: 'Free Fire',
      fulfillmentType: 'topup',
      minPrice: 15000,
    }),
  );
  assert.deepEqual(data.brand, { '@type': 'Brand', name: 'Garena Free Fire' });
});

test('Telegram JSON-LD brand is Telegram', () => {
  const data = productJsonLd(
    product({
      handle: 'telegram-premium-premium',
      title: 'Telegram Premium',
      fulfillmentType: 'telegram-premium',
      minPrice: 99000,
    }),
  );
  assert.deepEqual(data.brand, { '@type': 'Brand', name: 'Telegram' });
});

test('each Offer on a multi-price product has shipping and return policy', () => {
  const skus = [
    { price: 10000 } as CatalogSku,
    { price: 25000 } as CatalogSku,
  ];
  const data = productJsonLd(
    product({
      handle: 'pubg-mobile',
      title: 'پابجی موبایل',
      fulfillmentType: 'topup',
      skus,
    }),
    skus,
  );
  const offers = offersOf(data);
  assert.equal(offers.length, 2);
  for (const offer of offers) {
    assert.equal(offer['@type'], 'Offer');
    assert.equal(offer.priceCurrency, 'IRR');
    assert.equal((offer.shippingDetails as { '@type': string })['@type'], 'OfferShippingDetails');
    assert.equal(
      (offer.hasMerchantReturnPolicy as { returnPolicyCategory: string }).returnPolicyCategory,
      'https://schema.org/MerchantReturnNotPermitted',
    );
  }
  assert.equal(offers[0].price, 100000);
  assert.equal(offers[1].price, 250000);
});

test('does not invent aggregateRating from comment count', () => {
  const data = productJsonLd(
    product({
      handle: 'pubg-mobile',
      title: 'پابجی موبایل',
      fulfillmentType: 'topup',
      minPrice: 42000,
      comments_count: 12,
    }),
  );
  assert.equal('aggregateRating' in data, false);
  assert.equal('review' in data, false);
});

test('jsonLdBrandName uses Steam for steam gifts even if a holding publisher is stored', () => {
  assert.equal(
    jsonLdBrandName(
      product({
        handle: 'steam-gift-730',
        title: 'Counter-Strike 2',
        fulfillmentType: 'steam-gift',
        publisher: 'Valve',
      }),
    ),
    'Steam',
  );
});

test('jsonLdBrandName uses stored publisher when no catalog brand matches', () => {
  assert.equal(
    jsonLdBrandName(
      product({
        handle: 'gamekey-obscure-xyz',
        title: 'Obscure Game',
        fulfillmentType: 'gamekey',
        publisher: 'FromSoftware',
      }),
    ),
    'FromSoftware',
  );
});
