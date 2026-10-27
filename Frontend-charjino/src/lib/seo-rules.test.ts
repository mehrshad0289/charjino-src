import { test } from 'node:test';
import assert from 'node:assert/strict';
import { isIndexableProduct } from './seo-rules';

test('steam gifts need an explicit indexable flag', () => {
  const gift = {
    handle: 'steam-gift-730',
    title: 'Counter-Strike 2',
    fulfillmentType: 'steam-gift',
  };
  assert.equal(isIndexableProduct(gift), false);
  assert.equal(isIndexableProduct({ ...gift, indexable: false }), false);
  assert.equal(isIndexableProduct({ ...gift, indexable: true }), true);
});

test('non-gift products stay indexable unless flagged false', () => {
  const topup = {
    handle: 'pubg-mobile',
    title: 'پابجی موبایل',
    fulfillmentType: 'topup',
  };
  assert.equal(isIndexableProduct(topup), true);
  assert.equal(isIndexableProduct({ ...topup, indexable: true }), true);
  assert.equal(isIndexableProduct({ ...topup, indexable: false }), false);
});
