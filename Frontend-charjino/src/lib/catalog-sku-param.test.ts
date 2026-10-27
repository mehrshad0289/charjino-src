import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseSkuIdParam, preferredSkuId, resolveSelectedSkuId } from './catalog-sku-param';

test('parseSkuIdParam accepts positive integers only', () => {
  assert.equal(parseSkuIdParam('10513'), 10513);
  assert.equal(parseSkuIdParam(' 10514 '), 10514);
  assert.equal(parseSkuIdParam(null), null);
  assert.equal(parseSkuIdParam(''), null);
  assert.equal(parseSkuIdParam('abc'), null);
  assert.equal(parseSkuIdParam('10513abc'), null);
  assert.equal(parseSkuIdParam('0'), null);
  assert.equal(parseSkuIdParam('-12'), null);
});

test('preferredSkuId is null when missing or not on this product', () => {
  const skus = [{ id: 10513 }, { id: 10514 }, { id: 10522 }];
  assert.equal(preferredSkuId(skus, 10514), 10514);
  assert.equal(preferredSkuId(skus, 999), null);
  assert.equal(preferredSkuId(skus, null), null);
});

test('resolveSelectedSkuId keeps URL sku and falls back to first listed', () => {
  const skus = [{ id: 10513 }, { id: 10514 }, { id: 10522 }];
  const listed = [{ id: 10513 }, { id: 10514 }, { id: 10522 }];
  assert.equal(resolveSelectedSkuId(skus, listed, 10522), 10522);
  assert.equal(resolveSelectedSkuId(skus, listed, 1), 10513);
  assert.equal(resolveSelectedSkuId(skus, listed, null), 10513);
  assert.equal(resolveSelectedSkuId(skus, [], 10514), 10514);
  assert.equal(resolveSelectedSkuId([], [], null), null);
});
