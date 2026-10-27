import { test } from 'node:test';
import assert from 'node:assert/strict';
import { codmCountryFa, codmMatchCaption, codmMismatchCaption, codmSuggestedHref } from './codm-region';

test('codm country labels cover sold variants and Iran', () => {
  assert.equal(codmCountryFa('US'), 'آمریکا');
  assert.equal(codmCountryFa('ir'), 'ایران');
  assert.equal(codmCountryFa('KZ'), 'قزاقستان');
});

test('match caption includes nickname and region', () => {
  assert.match(
    codmMatchCaption({ nickname: 'badboy.2013', shortId: '2M2MXJ', accountCountry: 'US' }),
    /badboy\.2013/,
  );
  assert.match(codmMatchCaption({ nickname: 'badboy.2013', accountCountry: 'US' }), /آمریکا/);
});

test('mismatch without suggestion points to Iran block', () => {
  const text = codmMismatchCaption({ accountCountry: 'IR', variantCountry: 'US' });
  assert.match(text, /ایران/);
  assert.equal(codmSuggestedHref({ accountCountry: 'IR' }), null);
});

test('mismatch with CA suggests Canadian product', () => {
  const href = codmSuggestedHref({ suggestedVariant: 'CA' });
  assert.equal(href, '/catalog/topup-codm-activision-ca');
});
