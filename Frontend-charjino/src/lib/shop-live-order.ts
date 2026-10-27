import type { CatalogSku, Health } from '@/types/catalog';

const FAZER_FAMILIES = new Set([
  'topup',
  'giftcard',
  'gamekey',
  'steam-topup',
  'steam-gift',
  'telegram-stars',
  'telegram-premium',
  'manual',
]);

export function isFazerSku(sku: Pick<CatalogSku, 'fulfillmentType'>): boolean {
  return Boolean(sku.fulfillmentType && FAZER_FAMILIES.has(sku.fulfillmentType));
}

export function shopLiveOrderEnabled(
  health: Health | undefined,
  skus: Pick<CatalogSku, 'fulfillmentType'>[] = [],
): boolean {
  if (!health) return false;
  if (skus.some(isFazerSku)) {
    return Boolean(health.fazercardsLiveOrderEnabled);
  }

  return Boolean(health.liveOrderEnabled);
}

export function anySupplierLive(health: Health | undefined): boolean {
  if (!health) return false;

  return Boolean(health.liveOrderEnabled || health.fazercardsLiveOrderEnabled);
}
