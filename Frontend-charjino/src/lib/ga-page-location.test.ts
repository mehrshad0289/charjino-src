import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  GA_UNKNOWN,
  isSensitiveGaQueryKey,
  sanitizeGaPageLocation,
  sanitizeGaPathname,
} from './ga-page-location';
import { GA_PRIVACY_BOOT } from './ga-privacy-boot';

test('keeps public catalog URLs intact', () => {
  assert.equal(
    sanitizeGaPageLocation('https://charjino.ir/catalog/category/steam?q=pubg&page=2'),
    'https://charjino.ir/catalog/category/steam?q=pubg&page=2',
  );
});

test('redacts auth phone and profile order id', () => {
  const auth = new URL(
    sanitizeGaPageLocation('https://charjino.ir/auth?phone=09121234567&next=/profile'),
  );
  assert.equal(auth.origin + auth.pathname, 'https://charjino.ir/auth');
  assert.equal(auth.searchParams.get('phone'), GA_UNKNOWN);
  assert.equal(auth.searchParams.get('next'), '/profile');
  assert.equal(sanitizeGaPathname('/profile/orders/CH-1403-99'), `/profile/orders/${GA_UNKNOWN}`);
  assert.equal(sanitizeGaPathname('/profile/tickets/new'), '/profile/tickets/new');
  assert.equal(sanitizeGaPathname('/profile/tickets/8821'), `/profile/tickets/${GA_UNKNOWN}`);
  assert.equal(sanitizeGaPathname('/invoices/9921'), `/invoices/${GA_UNKNOWN}`);
  assert.equal(sanitizeGaPathname('/orders/9921'), `/orders/${GA_UNKNOWN}`);
});

test('redacts admin order, support, and user ids', () => {
  assert.equal(sanitizeGaPathname('/orders/CH-1403-99'), `/orders/${GA_UNKNOWN}`);
  assert.equal(sanitizeGaPathname('/support/441'), `/support/${GA_UNKNOWN}`);
  assert.equal(sanitizeGaPathname('/users/18'), `/users/${GA_UNKNOWN}`);
});

test('marks email and order query keys sensitive', () => {
  assert.equal(isSensitiveGaQueryKey('email'), true);
  assert.equal(isSensitiveGaQueryKey('order_id'), true);
  assert.equal(isSensitiveGaQueryKey('q'), false);
  assert.equal(isSensitiveGaQueryKey('page'), false);
});

test('privacy boot stays inline and uses نامعلوم', () => {
  assert.match(GA_PRIVACY_BOOT, /نامعلوم/);
  assert.match(GA_PRIVACY_BOOT, /dataLayer\.push/);
  assert.doesNotMatch(GA_PRIVACY_BOOT, /G-W90H63PDCT/);
});
