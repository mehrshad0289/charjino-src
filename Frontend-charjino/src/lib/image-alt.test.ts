import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  BRAND_LOGO_ALT,
  ENAMAD_SEAL_ALT,
  bannerImageAlt,
  categoryIconAlt,
  productImageAlt,
  uniqueImageAlt,
} from './image-alt';

test('logo alt token stays شارژینو', () => {
  assert.equal(BRAND_LOGO_ALT, 'شارژینو');
});

test('product photos use name and Persian region once, without brand suffix', () => {
  assert.equal(productImageAlt('کال اف دیوتی موبایل'), 'کال اف دیوتی موبایل');
  assert.equal(productImageAlt('کال اف دیوتی موبایل', 'آمریکا'), 'کال اف دیوتی موبایل آمریکا');
  assert.equal(productImageAlt('کال اف دیوتی موبایل آمریکا', 'آمریکا'), 'کال اف دیوتی موبایل آمریکا');
  assert.equal(productImageAlt('کال اف دیوتی موبایل – شارژینو'), 'کال اف دیوتی موبایل');
  assert.equal(productImageAlt('گیفت کارت', 'GLOBAL'), 'گیفت کارت جهانی');
  assert.equal(productImageAlt('گیفت کارت', 'US'), 'گیفت کارت آمریکا');
  assert.equal(productImageAlt('گیفت کارت', 'CIS'), 'گیفت کارت سی‌آی‌اس');
});

test('category icons: named stay, generic become دسته\u200cبندی', () => {
  assert.equal(categoryIconAlt('شارژ بازی'), 'شارژ بازی');
  assert.equal(categoryIconAlt('بازی'), 'دسته‌بندی بازی در شارژینو');
  assert.equal(categoryIconAlt('تلگرام'), 'دسته‌بندی تلگرام در شارژینو');
  assert.equal(categoryIconAlt('گیفت کارت'), 'دسته‌بندی گیفت کارت در شارژینو');
});

test('duplicate alts on a page become decorative', () => {
  const seen = new Set<string>();
  assert.deepEqual(uniqueImageAlt('گیفت کارت آمریکا', seen), { alt: 'گیفت کارت آمریکا', decorative: false });
  assert.deepEqual(uniqueImageAlt('گیفت کارت آمریکا', seen), { alt: '', decorative: true });
});

test('banners use the banner title', () => {
  assert.equal(
    bannerImageAlt({ title: 'فروش ویژه پابجی', description: 'خرید UC پابجی با تخفیف' }),
    'فروش ویژه پابجی',
  );
  assert.equal(bannerImageAlt({ title: 'استار تلگرام', description: '' }), 'استار تلگرام');
});

test('enamad seal is named', () => {
  assert.equal(ENAMAD_SEAL_ALT, 'نماد اعتماد الکترونیک');
});
