import { test } from 'node:test';
import assert from 'node:assert/strict';
import { toPlainText } from './to-plain-text';

test('meta description has no markdown', () => {
  const raw = '**خرید** الماس از شارژینو با [لینک](https://midasbuy.com)';
  const plain = toPlainText(raw);
  assert.equal(plain.includes('**'), false);
  assert.equal(plain.includes('['), false);
  assert.match(plain, /خرید الماس از شارژینو/);
});
