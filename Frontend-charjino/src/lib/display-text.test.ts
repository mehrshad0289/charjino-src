import { test } from 'node:test';
import assert from 'node:assert/strict';
import { normalizeVisibleText, splitLeadingEmoji } from './display-text';

test('normalizes spacing and parentheses on visible headings', () => {
  assert.equal(normalizeVisibleText('  پلی استیشن( آمریکا)  '), 'پلی‌استیشن (آمریکا)');
  assert.equal(normalizeVisibleText('فروش   ویژه'), 'فروش ویژه');
});

test('splits a leading emoji for aria-hidden wrapping', () => {
  assert.deepEqual(splitLeadingEmoji('🔥 محبوب‌های امروز'), {
    emoji: '🔥',
    text: 'محبوب‌های امروز',
  });
  assert.deepEqual(splitLeadingEmoji('سؤالات پرتکرار'), {
    emoji: '',
    text: 'سؤالات پرتکرار',
  });
});
