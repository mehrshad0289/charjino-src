'use server';

import { ApiError, serverFetch } from '@/lib/server-fetcher';
import { apiPaths } from '@/lib/api-paths';
import type {
  AdminGroup,
  AdminProduct,
  AdminSku,
  GiftCardOption,
  AdminUserRow,
  AdminBanner,
  AdminFlashSaleItem,
  AdminHomePayload,
  PricingSnapshot,
  SupplierStatus,
  AdminOrder,
  AdminDashboardSummary,
  CatalogSyncStatus,
  FazerCardsStatus,
  FazerCardsProxyResult,
  FazerCardsAuditRow,
  SteamGiftSearchHit,
  FazerCatalogSearchHit
} from '@/types/admin';

function toActionMessage(error: unknown) {
  if (error instanceof ApiError && error.message) return error.message;
  if (error instanceof Error && error.message) return error.message;
  return 'درخواست انجام نشد';
}

function withQuery(path: string, params: Record<string, string | number | undefined | null>) {
  const qs = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '' || value === 'all') continue;
    qs.set(key, String(value));
  }
  const suffix = qs.toString();
  return suffix ? `${path}?${suffix}` : path;
}

export async function listUsers(filters?: { page?: number; perPage?: number; role?: string }) {
  try {
    const data = await serverFetch<{
      items: AdminUserRow[];
      meta?: { page: number; perPage: number; total: number; totalPages: number };
    }>(withQuery(apiPaths.users, { page: filters?.page, perPage: filters?.perPage, role: filters?.role }));
    return { ok: true as const, items: data.items, meta: data.meta };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function listGroups(
  sourceOrFilters?: string | { source?: string; page?: number; perPage?: number }
) {
  try {
    const filters = typeof sourceOrFilters === 'object' && sourceOrFilters ? sourceOrFilters : { source: sourceOrFilters };
    const data = await serverFetch<{
      items: AdminGroup[];
      meta?: { page: number; perPage: number; total: number; totalPages: number };
    }>(withQuery(apiPaths.catalogGroups, filters));
    return { ok: true as const, items: data.items, meta: data.meta };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function getGroup(id: number) {
  try {
    const data = await serverFetch<AdminGroup>(apiPaths.catalogGroup(id));
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function createGroup(payload: Partial<AdminGroup> & { title: string }) {
  try {
    const data = await serverFetch<AdminGroup>(apiPaths.catalogGroups, {
      method: 'POST',
      body: JSON.stringify({
        title: payload.title,
        nameEn: payload.nameEn,
        slug: payload.slug,
        description: payload.description,
        purchaseGuide: payload.purchaseGuide,
        purchaseGuideItems: payload.purchaseGuideItems,
        imageUrl: payload.imageUrl,
        visible: payload.visible,
        sortOrder: payload.sortOrder
      })
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function updateGroup(id: number, payload: Partial<AdminGroup>) {
  try {
    const data = await serverFetch<AdminGroup>(apiPaths.catalogGroup(id), {
      method: 'PUT',
      body: JSON.stringify({
        title: payload.title,
        nameEn: payload.nameEn,
        slug: payload.slug,
        description: payload.description,
        seoTitle: payload.seoTitle,
        seoDescription: payload.seoDescription,
        purchaseGuide: payload.purchaseGuide,
        purchaseGuideItems: payload.purchaseGuideItems,
        imageUrl: payload.imageUrl,
        visible: payload.visible,
        sortOrder: payload.sortOrder
      })
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function deleteGroup(id: number) {
  try {
    const data = await serverFetch<AdminGroup & { id: number }>(apiPaths.catalogGroup(id), { method: 'DELETE' });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function purgeShop2Catalog() {
  try {
    const data = await serverFetch<{ groups: number; products: number; skus: number }>(apiPaths.catalogShop2, {
      method: 'DELETE'
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function purgeUnlinkedGroups() {
  try {
    const data = await serverFetch<{ deleted: number; ids: number[] }>(apiPaths.catalogGroupsUnlinked, {
      method: 'DELETE'
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function listProducts(filters?: {
  category?: string | number;
  status?: string;
  q?: string;
  page?: number;
  perPage?: number;
  visible?: string;
  source?: string;
  platform?: string;
  region?: string;
}) {
  try {
    const data = await serverFetch<{
      items: AdminProduct[];
      meta: {
        page: number;
        perPage: number;
        total: number;
        totalPages: number;
        platforms?: string[];
        regions?: string[];
      };
    }>(
      withQuery(apiPaths.catalogProducts, {
        category: filters?.category,
        status: filters?.status,
        q: filters?.q,
        page: filters?.page ?? 1,
        perPage: filters?.perPage ?? 10,
        visible: filters?.visible,
        source: filters?.source,
        platform: filters?.platform,
        region: filters?.region,
      })
    );
    return { ok: true as const, items: data.items, meta: data.meta };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function getProduct(id: number) {
  try {
    const data = await serverFetch<AdminProduct>(apiPaths.catalogProduct(id));
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function hideProduct(id: number, substituteProductId?: number | null) {
  try {
    const data = await serverFetch<AdminProduct>(apiPaths.catalogProduct(id), {
      method: 'DELETE',
      body: JSON.stringify({ substituteProductId: substituteProductId ?? null })
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function refreshProductRequirements(id: number) {
  try {
    const data = await serverFetch<AdminProduct>(apiPaths.catalogProductRequirements(id), { method: 'POST' });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function updateProduct(id: number, payload: Partial<AdminProduct>) {
  try {
    const data = await serverFetch<AdminProduct>(apiPaths.catalogProduct(id), {
      method: 'PUT',
      body: JSON.stringify({
        title: payload.title,
        nameEn: payload.nameEn,
        subtitle: payload.subtitle,
        seoTitle: payload.seoTitle,
        seoDescription: payload.seoDescription,
        description: payload.description,
        publisher: payload.publisher,
        imageUrl: payload.imageUrl,
        isPopular: payload.isPopular,
        visible: payload.visible,
        status: payload.status,
        sortOrder: payload.sortOrder,
        catalogGroupId: payload.catalogGroupId,
        handle: payload.handle,
        fazercardsCategoryId: payload.fazercardsCategoryId,
        searchTags: payload.searchTags,
        ...(payload.marginPercent !== undefined
          ? { marginPercent: payload.marginPercent === '' || payload.marginPercent === null ? null : Number(payload.marginPercent) }
          : {}),
        ...('clearSkuMargins' in payload && payload.clearSkuMargins !== undefined
          ? { clearSkuMargins: Boolean(payload.clearSkuMargins) }
          : {}),
        ...('regionNote' in payload ? { regionNote: (payload as { regionNote?: string | null }).regionNote } : {}),
        ...('clearExcluded' in payload ? { clearExcluded: Boolean((payload as { clearExcluded?: boolean }).clearExcluded) } : {}),
        ...('substituteProductId' in payload ? { substituteProductId: payload.substituteProductId ?? null } : {}),
        ...('purchaseSchema' in payload ? { purchaseSchema: payload.purchaseSchema } : {})
      })
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function updateSku(id: number, payload: Partial<AdminSku>) {
  try {
    const data = await serverFetch<AdminSku>(apiPaths.catalogSku(id), {
      method: 'PUT',
      body: JSON.stringify({
        name: payload.name,
        nameEn: payload.nameEn,
        visible: payload.visible,
        status: payload.status,
        ...(payload.price !== undefined ? { price: payload.price } : {}),
        ...(payload.marginPercent !== undefined
          ? { marginPercent: payload.marginPercent === '' || payload.marginPercent === null ? null : Number(payload.marginPercent) }
          : {}),
        salePrice: payload.salePrice,
        saleEndsAt: payload.saleEndsAt,
        fazercardsCategoryId: payload.fazercardsCategoryId,
        fazercardsOfferId: payload.fazercardsOfferId,
        ...(payload.costUsd !== undefined ? { costUsd: payload.costUsd } : {}),
        ...(payload.giftSkuId !== undefined ? { giftSkuId: payload.giftSkuId } : {})
      })
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function createGiftProduct(payload: {
  title: string;
  nameEn: string;
  catalogGroupId: number;
  platform: string;
  region?: string;
  publisher?: string;
  description?: string;
  imageUrl?: string;
}) {
  try {
    const data = await serverFetch<AdminProduct>(apiPaths.catalogProducts, {
      method: 'POST',
      body: JSON.stringify(payload)
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function createGiftSku(
  productId: number,
  payload: { name: string; nameEn: string; costUsd: string; giftSkuId: number }
) {
  try {
    const data = await serverFetch<AdminSku>(apiPaths.catalogProductSkus(productId), {
      method: 'POST',
      body: JSON.stringify(payload)
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function listGiftCards(filters?: { platform?: string | null; region?: string | null; q?: string }) {
  try {
    const data = await serverFetch<{ items: GiftCardOption[] }>(
      withQuery(apiPaths.catalogGiftCards, {
        platform: filters?.platform || undefined,
        region: filters?.region || undefined,
        q: filters?.q || undefined
      })
    );
    return { ok: true as const, items: data.items };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function listOrders(filters?: {
  status?: string;
  q?: string;
  page?: number;
  perPage?: number;
  from?: string;
  to?: string;
  unknown?: boolean;
  hold?: boolean;
}) {
  try {
    const data = await serverFetch<{
      items: AdminOrder[];
      meta: {
        page: number;
        perPage: number;
        total: number;
        totalPages: number;
        needsReview?: number;
        refundPending?: number;
        unknownAttempts?: number;
        hold?: number;
      };
    }>(
      withQuery(apiPaths.adminOrders, {
        status: filters?.status,
        q: filters?.q,
        page: filters?.page ?? 1,
        perPage: filters?.perPage ?? 10,
        from: filters?.from,
        to: filters?.to,
        unknown: filters?.unknown ? '1' : undefined,
        hold: filters?.hold ? '1' : undefined
      })
    );
    return { ok: true as const, items: data.items, meta: data.meta };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function getOrder(id: string) {
  try {
    const data = await serverFetch<AdminOrder>(apiPaths.adminOrder(id));
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function refundOrder(
  id: string,
  payload: { method?: 'wallet' | 'manual_gateway'; note?: string; externalRef?: string; reason?: string } = {}
) {
  try {
    const data = await serverFetch<AdminOrder>(apiPaths.adminOrderRefund(id), {
      method: 'POST',
      body: JSON.stringify({
        method: payload.method ?? 'wallet',
        note: payload.note ?? payload.reason,
        externalRef: payload.externalRef
      })
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function cancelOrder(id: string) {
  try {
    const data = await serverFetch<AdminOrder>(apiPaths.adminOrderCancel(id), { method: 'POST' });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function retryFulfillment(id: string) {
  try {
    const data = await serverFetch<AdminOrder>(apiPaths.adminOrderRedeliver(id), { method: 'POST' });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function refreshOrderFromFazer(id: string) {
  try {
    const data = await serverFetch<AdminOrder>(apiPaths.adminOrderRefresh(id), { method: 'POST' });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function linkFazerOrder(id: string, fazerOrderId: string) {
  try {
    const data = await serverFetch<AdminOrder>(apiPaths.adminOrderLink(id), {
      method: 'POST',
      body: JSON.stringify({ fazerOrderId })
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function abandonFazerOrder(id: string, reason: string) {
  try {
    const data = await serverFetch<AdminOrder>(apiPaths.adminOrderAbandon(id), {
      method: 'POST',
      body: JSON.stringify({ reason })
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function revealAdminOrder(id: string) {
  try {
    const data = await serverFetch<{ kind: string; items: string[] }>(apiPaths.adminOrderReveal(id), { method: 'POST' });
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function listAdminAlerts() {
  try {
    const data = await serverFetch<{ items: Array<{ id: number; type: string; openedAt?: string | null; payload?: unknown }> }>(
      apiPaths.adminAlerts
    );
    return { ok: true as const, items: data.items };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function resolveAdminAlert(id: number) {
  try {
    await serverFetch(apiPaths.adminAlertResolve(id), { method: 'POST' });
    return { ok: true as const };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function listExcludedProducts(page = 1) {
  try {
    const data = await serverFetch<{ items: AdminProduct[]; meta: { page: number; perPage: number; total: number; totalPages: number } }>(
      withQuery(apiPaths.catalogExcluded, { page })
    );
    return { ok: true as const, items: data.items, meta: data.meta };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function getDashboardSummary() {
  try {
    const data = await serverFetch<AdminDashboardSummary>(apiPaths.dashboardSummary);
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function getSupplierStatus() {
  try {
    const data = await serverFetch<SupplierStatus>(apiPaths.supplier);
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function getPricing() {
  try {
    const data = await serverFetch<PricingSnapshot>(apiPaths.settings);
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function savePricing(payload: {
  usdTomanRate?: string;
  marginPercent?: string;
  fxMode?: string;
}) {
  try {
    const data = await serverFetch<PricingSnapshot>(apiPaths.settings, {
      method: 'PUT',
      body: JSON.stringify({
        usdTomanRate: payload.usdTomanRate ? Number(payload.usdTomanRate) : undefined,
        marginPercent: payload.marginPercent ? Number(payload.marginPercent) : undefined,
        fxMode: payload.fxMode
      })
    });
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function refreshFx() {
  try {
    const data = await serverFetch<PricingSnapshot>(apiPaths.settingsFx, { method: 'POST' });
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function uploadCatalogImage(formData: FormData) {
  try {
    const data = await serverFetch<{ url: string; path: string; mediaType?: string }>(apiPaths.uploads, {
      method: 'POST',
      body: formData
    });
    return { ok: true as const, url: data.url, mediaType: data.mediaType };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function getHomeCms() {
  try {
    const data = await serverFetch<AdminHomePayload>(apiPaths.home);
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function saveHomeCopy(payload: Partial<AdminHomePayload>) {
  try {
    const data = await serverFetch<Pick<
      AdminHomePayload,
      'flashSaleTitle' | 'flashSaleSubtitle' | 'flashSaleEndsAt' | 'popularTitle' | 'popularSubtitle'
    >>(apiPaths.homeCopy, {
      method: 'PUT',
      body: JSON.stringify({
        flashSaleTitle: payload.flashSaleTitle,
        flashSaleSubtitle: payload.flashSaleSubtitle,
        flashSaleEndsAt: payload.flashSaleEndsAt,
        popularTitle: payload.popularTitle,
        popularSubtitle: payload.popularSubtitle
      })
    });
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function saveHomeSections(
  sections: Array<{ groupId: number; enabled: boolean; limit: number; productIds: number[] }>
) {
  try {
    const data = await serverFetch<{ sections: NonNullable<AdminHomePayload['sections']> }>(apiPaths.homeSections, {
      method: 'PUT',
      body: JSON.stringify({ sections })
    });
    return { ok: true as const, sections: data.sections ?? [] };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function saveBanner(payload: Partial<AdminBanner> & { id?: number }) {
  try {
    const path = payload.id ? apiPaths.banner(payload.id) : apiPaths.banners;
    const data = await serverFetch<AdminBanner>(path, {
      method: payload.id ? 'PUT' : 'POST',
      body: JSON.stringify({
        src: payload.src,
        title: payload.title,
        href: payload.href,
        description: payload.description,
        mediaType: payload.mediaType,
        visible: payload.visible,
        sortOrder: payload.sortOrder
      })
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function deleteBanner(id: number) {
  try {
    await serverFetch<{ id: number }>(apiPaths.banner(id), { method: 'DELETE' });
    return { ok: true as const };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function saveFlashSaleItem(payload: Partial<AdminFlashSaleItem> & { id?: number }) {
  try {
    const path = payload.id ? apiPaths.flashSaleItem(payload.id) : apiPaths.flashSale;
    const data = await serverFetch<AdminFlashSaleItem>(path, {
      method: payload.id ? 'PUT' : 'POST',
      body: JSON.stringify({
        skuId: payload.skuId ?? undefined,
        price: payload.price,
        originalPrice: payload.originalPrice,
        image: payload.image,
        visible: payload.visible,
        sortOrder: payload.sortOrder
      })
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function deleteFlashSaleItem(id: number) {
  try {
    await serverFetch<{ id: number }>(apiPaths.flashSaleItem(id), { method: 'DELETE' });
    return { ok: true as const };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function getFazerCardsStatus() {
  try {
    const data = await serverFetch<FazerCardsStatus>(apiPaths.fazercards);
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function saveFazerCardsKey(apiKey: string) {
  try {
    const data = await serverFetch<Pick<FazerCardsStatus, 'configured' | 'keyLast4'>>(apiPaths.fazercardsKey, {
      method: 'PUT',
      body: JSON.stringify({ apiKey })
    });
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function clearFazerCardsKey() {
  try {
    const data = await serverFetch<Pick<FazerCardsStatus, 'configured' | 'keyLast4'>>(apiPaths.fazercardsKey, {
      method: 'DELETE'
    });
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function saveFazerCardsSettings(allowLiveOrder: boolean) {
  try {
    const data = await serverFetch<Pick<FazerCardsStatus, 'liveOrderEnabled' | 'webhookConfigured'>>(
      apiPaths.fazercardsSettings,
      {
        method: 'PUT',
        body: JSON.stringify({ allowLiveOrder })
      }
    );
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function saveSteamGiftBlocklist(steamGiftBlockedWords: string) {
  try {
    const data = await serverFetch<Pick<FazerCardsStatus, 'steamGiftBlockedWords'>>(
      apiPaths.fazercardsSteamGiftBlocklist,
      {
        method: 'PUT',
        body: JSON.stringify({ steamGiftBlockedWords })
      }
    );
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function getFazerCardsAudits() {
  try {
    const data = await serverFetch<{ items: FazerCardsAuditRow[] }>(apiPaths.fazercardsAudits);
    return { ok: true as const, items: data.items };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function syncFazerCardsCatalog(family?: string) {
  try {
    const data = await serverFetch<CatalogSyncStatus>(apiPaths.fazercardsSync, {
      method: 'POST',
      body: family ? JSON.stringify({ family }) : undefined
    });
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function cancelFazerCardsCatalogSync() {
  try {
    const data = await serverFetch<CatalogSyncStatus>(apiPaths.fazercardsSyncCancel, { method: 'POST' });
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function getFazerCardsSyncStatus() {
  try {
    const data = await serverFetch<CatalogSyncStatus>(apiPaths.fazercardsSync);
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function searchFazerSteamGifts(query: string) {
  try {
    const data = await serverFetch<{ items: SteamGiftSearchHit[]; shopMax: number }>(
      withQuery(apiPaths.fazercardsSteamGiftsSearch, { q: query })
    );
    return { ok: true as const, items: data.items, shopMax: data.shopMax };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function importFazerSteamGift(appId: number) {
  try {
    const data = await serverFetch<AdminProduct>(apiPaths.fazercardsSteamGiftsImport, {
      method: 'POST',
      body: JSON.stringify({ appId })
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function searchFazerCatalog(family: string, query: string, platform?: string) {
  try {
    const data = await serverFetch<{
      items: FazerCatalogSearchHit[];
      shopMax: number;
      perPlatform?: number | null;
      platforms?: string[];
    }>(withQuery(apiPaths.fazercardsCatalogSearch, { family, q: query, platform }));
    return {
      ok: true as const,
      items: data.items,
      shopMax: data.shopMax,
      perPlatform: data.perPlatform ?? null,
      platforms: data.platforms ?? []
    };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function importFazerCatalog(family: string, remoteId: string) {
  try {
    const data = await serverFetch<AdminProduct>(apiPaths.fazercardsCatalogImport, {
      method: 'POST',
      body: JSON.stringify({ family, remoteId })
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function proxyFazerCards(
  method: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE',
  path: string,
  opts?: { query?: Record<string, string>; body?: unknown }
) {
  try {
    const qs = new URLSearchParams();
    for (const [key, value] of Object.entries(opts?.query ?? {})) {
      if (value) qs.set(key, value);
    }
    const suffix = qs.toString();
    const data = await serverFetch<FazerCardsProxyResult>(
      `${apiPaths.fazercardsProxy(path)}${suffix ? `?${suffix}` : ''}`,
      {
        method,
        body: method === 'GET' || method === 'DELETE' ? undefined : JSON.stringify(opts?.body ?? {})
      }
    );
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export type AdminSearchSynonym = {
  id: number;
  term: string;
  synonyms: string[];
  group_slug: string | null;
  is_active: boolean;
};

export type AdminSearchHealth = {
  driver: string;
  prefix: string;
  meili?: string;
  indexes: Record<string, { uid: string; documents?: number; missing?: boolean; isIndexing?: boolean }>;
};

export async function listSearchSynonyms() {
  try {
    const data = await serverFetch<AdminSearchSynonym[]>(apiPaths.searchSynonyms);
    return { ok: true as const, items: Array.isArray(data) ? data : [] };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error), items: [] as AdminSearchSynonym[] };
  }
}

export async function saveSearchSynonym(payload: {
  id?: number;
  term: string;
  synonyms: string;
  group_slug?: string;
  is_active: boolean;
}) {
  try {
    const body = {
      term: payload.term,
      synonyms: payload.synonyms.split(/[,\n]/).map((row) => row.trim()).filter(Boolean),
      group_slug: payload.group_slug || null,
      is_active: payload.is_active
    };
    const data = payload.id
      ? await serverFetch<AdminSearchSynonym>(apiPaths.searchSynonym(payload.id), {
          method: 'PUT',
          body: JSON.stringify(body)
        })
      : await serverFetch<AdminSearchSynonym>(apiPaths.searchSynonyms, {
          method: 'POST',
          body: JSON.stringify(body)
        });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function deleteSearchSynonym(id: number) {
  try {
    await serverFetch(apiPaths.searchSynonym(id), { method: 'DELETE' });
    return { ok: true as const };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function getSearchHealth() {
  try {
    const data = await serverFetch<AdminSearchHealth>(apiPaths.searchHealth);
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function syncSearchSynonyms() {
  try {
    const data = await serverFetch<{ synonyms: number }>(apiPaths.searchSyncSynonyms, { method: 'POST' });
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export type AdminSearchTag = {
  id: number;
  slug: string;
  label: string;
  kind?: string;
  is_active?: boolean;
  sort_order?: number;
  catalog_group_id?: number | null;
  aliases?: string[];
};

export async function listSearchTags() {
  try {
    const data = await serverFetch<AdminSearchTag[]>(apiPaths.searchTags);
    return { ok: true as const, items: Array.isArray(data) ? data : [] };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error), items: [] as AdminSearchTag[] };
  }
}

export async function updateSearchTag(
  id: number,
  payload: { kind: string; is_active: boolean; sort_order: string; aliases: string },
) {
  try {
    const data = await serverFetch<AdminSearchTag>(apiPaths.searchTag(id), {
      method: 'PUT',
      body: JSON.stringify({
        kind: payload.kind,
        is_active: payload.is_active,
        sort_order: Number.parseInt(payload.sort_order, 10) || 0,
        aliases: payload.aliases.split(/[,،\n]/).map((row) => row.trim()).filter(Boolean),
      }),
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}
