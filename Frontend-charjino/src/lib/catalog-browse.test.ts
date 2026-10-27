import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  browseCanonicalPath,
  browseQueryEquals,
  browseShouldNoindex,
  categoryFromBrowsePath,
  parseBrowseQuery,
} from './catalog-browse';

test('page>1, search, and filter queries are noindex; clean category stays indexable', () => {
  assert.equal(
    browseShouldNoindex(parseBrowseQuery({ page: '2' })),
    true,
  );
  assert.equal(
    browseShouldNoindex(parseBrowseQuery({ page: '1' })),
    false,
  );
  assert.equal(
    browseShouldNoindex(parseBrowseQuery({ q: 'pubg' })),
    true,
  );
  assert.equal(
    browseShouldNoindex(parseBrowseQuery({ platform: 'steam' })),
    true,
  );
  assert.equal(
    browseShouldNoindex(parseBrowseQuery({ sort: 'price' })),
    true,
  );
});

test('category path and browse query equality', () => {
  assert.equal(categoryFromBrowsePath('/catalog/category/game-keys'), 'game-keys');
  assert.equal(categoryFromBrowsePath('/catalog/category'), '');
  assert.equal(categoryFromBrowsePath('/catalog/topup-pubg-mobile'), '');
  assert.equal(
    browseQueryEquals(
      parseBrowseQuery({ platform: 'steam', region: 'global' }),
      parseBrowseQuery({ platform: 'steam', region: 'global' }),
    ),
    true,
  );
  assert.equal(
    browseQueryEquals(
      parseBrowseQuery({ platform: 'steam' }),
      parseBrowseQuery({ platform: '' }),
    ),
    false,
  );
});

test('browse canonical is always the clean path', () => {
  const parsed = parseBrowseQuery({
    category: 'steam-gifts',
    page: '7',
    sort: 'price',
    platform: 'steam',
    q: 'hole',
  });
  assert.equal(browseCanonicalPath('/catalog/category/steam-gifts', parsed), '/catalog/category/steam-gifts');
  assert.equal(browseCanonicalPath('/catalog/category', parsed), '/catalog/category');
});
