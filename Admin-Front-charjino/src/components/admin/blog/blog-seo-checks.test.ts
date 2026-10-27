import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
  altCheckOk,
  buildSeoChecks,
  hasInternalLink,
  isInternalHref
} from './blog-seo-checks';
import { formatFaqField, parseFaqField } from './blog-faq';
import { keywordMatchesSlug, latinizeSlug, slugifyTitle } from './blog-slug';

describe('blog seo checks', () => {
  it('warns when focus keyword is empty instead of greening keyword checks', () => {
    const items = buildSeoChecks({
      title: 'راهنمای خرید یوسی پابجی از شارژینو',
      slug: 'pubg-uc',
      excerpt: '',
      content: '<h2>خرید</h2><p>متن</p><a href="/blog/x">لینک</a>',
      metaTitle: '',
      metaDescription: '',
      focusKeyword: '',
      cover: '',
      coverAlt: ''
    });
    const keywordItems = items.filter((item) => item.text.includes('کلیدواژه') || item.text.includes('کلمه کلیدی'));
    assert.ok(keywordItems.length >= 4);
    assert.ok(keywordItems.every((item) => item.status !== 'ok'));
    assert.ok(keywordItems.some((item) => item.text === 'کلیدواژه را وارد کنید'));
  });

  it('counts absolute charjino.ir links as internal', () => {
    assert.equal(isInternalHref('/blog/guide'), true);
    assert.equal(isInternalHref('https://charjino.ir/blog/guide'), true);
    assert.equal(isInternalHref('https://www.charjino.ir/catalog/x'), true);
    assert.equal(isInternalHref('https://example.com/blog'), false);
    assert.equal(
      hasInternalLink('<p>لینک <a href="https://charjino.ir/blog/guide">راهنما</a></p>'),
      true
    );
  });

  it('scores body image alts and ignores missing cover when there is no cover', () => {
    assert.equal(
      altCheckOk({
        content: '<p><img src="/storage/blog/a.webp" alt="خرید یوسی"></p>',
        cover: '',
        coverAlt: ''
      }),
      true
    );
    assert.equal(
      altCheckOk({
        content: '<p><img src="/storage/blog/a.webp" alt="خرید یوسی"></p>',
        cover: '/storage/blog/cover.webp',
        coverAlt: ''
      }),
      false
    );
    assert.equal(
      altCheckOk({
        content: '<p><img src="/storage/blog/a.webp"></p>',
        cover: '',
        coverAlt: ''
      }),
      false
    );
  });

  it('marks latin slug keyword check as info unless latin equivalent matches', () => {
    const miss = buildSeoChecks({
      title: 'آموزش خرید',
      slug: 'buy-guide',
      excerpt: '',
      content: '<p>متن</p>',
      metaTitle: '',
      metaDescription: '',
      focusKeyword: 'آموزش خرید',
      cover: '',
      coverAlt: ''
    });
    const slugItem = miss.find((item) => item.text.includes('نامک'));
    assert.equal(slugItem?.status, 'info');
    assert.equal(keywordMatchesSlug('یوسی', latinizeSlug('یوسی')), true);
    assert.equal(slugifyTitle('آموزش خرید یوسی'), 'آموزش-خرید-یوسی');
  });
});

describe('faq parse', () => {
  it('accepts single pipe and double pipe lines', () => {
    const items = parseFaqField('سؤال یک|جواب یک\nسؤال دو||جواب دو');
    assert.equal(items.length, 2);
    assert.equal(items[0]?.question, 'سؤال یک');
    assert.equal(items[1]?.answer, 'جواب دو');
    assert.ok(formatFaqField(items).includes(' | '));
  });
});
