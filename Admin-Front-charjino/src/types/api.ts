import type { components } from '@/types/api.gen';

export type ApiResponse<T> = Omit<components['schemas']['SuccessEnvelope'], 'data'> & {
  data?: T;
};

export type ApiErrorResponse = components['schemas']['ErrorResponse'];

export type AdminCategory = components['schemas']['AdminCategory'];
export type AdminGroup = AdminCategory & {
  synced?: boolean;
  source?: 'manual' | 'shop2' | 'fazercards';
  purchaseGuide?: string | null;
  purchaseGuideItems?: import('@/lib/purchase-guide').PurchaseGuideItem[];
  seoTitle?: string | null;
  seoDescription?: string | null;
};
export type AdminSku = components['schemas']['AdminSku'] & {
  fazercardsCategoryId?: string | null;
  fazercardsOfferId?: string | null;
  giftSkuId?: number | null;
  costUsd?: string | null;
  marginPercent?: string | null;
  effectiveMarginPercent?: string | null;
  marginSource?: 'sku' | 'product' | 'global';
};
export type AdminProduct = Omit<components['schemas']['AdminProduct'], 'skus'> & {
  seoTitle?: string | null;
  seoDescription?: string | null;
  fazercardsCategoryId?: string | null;
  source?: 'manual' | 'shop2' | 'fazercards';
  platform?: string | null;
  region?: string | null;
  giftProxy?: boolean;
  searchTags?: string[];
  marginPercent?: string | null;
  globalMarginPercent?: string | null;
  clearSkuMargins?: boolean;
  excludedReason?: string | null;
  regionNote?: string | null;
  clearExcluded?: boolean;
  purchaseSchema?: {
    type?: string;
    regionNote?: string | null;
    fields?: Array<{ key?: string; label?: string; labelFa?: string; help?: string | null }>;
  } | null;
  syncedAt?: string | null;
  substituteProductId?: number | null;
  skus: AdminSku[];
};

export type AdminOrder = {
  id: string;
  number: string;
  status: string;
  statusLabelFa?: string;
  fulfillmentType?: string | null;
  amountToman: number;
  subtotalToman?: number;
  discountToman?: number;
  couponCode?: string | null;
  quantity: number;
  playerName: string | null;
  skuName: string | null;
  productTitle: string | null;
  customerMessage: string | null;
  userPhoneNumber?: string | null;
  skuId?: number | null;
  productId?: number | null;
  requirements?: Record<string, string | number | null> | null;
  costUsdSnapshot?: string;
  fxRateSnapshot?: string;
  holdReason?: string | null;
  holdUntil?: string | null;
  needsReviewReason?: string | null;
  expiresAt?: string | null;
  createdAt: string | null;
  timeline?: Array<{ type?: string; at?: string; status?: string; note?: string; code?: string }>;
  items?: Array<{
    id: number;
    fulfillmentType?: string | null;
    quantity: number;
    unitPriceToman?: number;
    totalToman?: number;
    unitCostUsd?: string;
    totalCostUsd?: string;
    fxRate?: string;
    marginPercent?: string;
    steamCurrency?: string | null;
    steamAmount?: string | null;
  }>;
  payments?: Array<{
    id: number;
    provider: string;
    status: string;
    amountToman: number;
    authority?: string | null;
    ref?: string | null;
    createdAt?: string | null;
  }>;
  fulfillmentAttempts?: Array<{
    id: number;
    attemptNo?: number;
    supplier?: string;
    supplierOrderId: string;
    fazerOrderId?: string | null;
    fazerKind?: string | null;
    fazerStatus?: string | null;
    httpStatus?: number | null;
    failReason?: string | null;
    idempotencyKeyShort?: string | null;
    status: string;
    dryRun: boolean;
    errorCode: string | null;
    expectedUnitPrice?: string | null;
    pollCount?: number;
    reconcileCount?: number;
    nextPollAt?: string | null;
    lastPolledAt?: string | null;
    submittedAt?: string | null;
    requestRedacted?: unknown;
    responseRedacted?: unknown;
  }>;
  transitions?: Array<{ from?: string; to?: string; actor?: string; code?: string; note?: string; createdAt?: string }>;
  webhookEvents?: Array<{ event?: string; eventId?: string; dataStatus?: string; receivedAt?: string | null }>;
  refunds?: Array<{ id: number; method: string; status: string; amountToman: number; note?: string | null }>;
};

export type AdminDashboardSummary = {
  ordersToday: number;
  revenueTodayToman: number;
  pendingFulfillment: number;
  failedToday: number;
  needsReview?: number;
  refundPending?: number;
  unknownAttempts?: number;
  hold?: number;
  openAlerts?: number;
  supplierWallet: string | null;
  fazercardsWallet?: string | null;
  supplierWalletLow?: boolean;
  accountError?: string | null;
  accountErrorCode?: string | null;
};

export type FazerCardsCatalogScope = {
  id: string;
  title: string;
  titleEn: string;
};

export type CatalogSyncStatus = {
  status: 'idle' | 'running' | 'completed' | 'failed' | 'cancelled' | string;
  phase?: string | null;
  phaseLabel?: string | null;
  scope?: string | null;
  scopeLabel?: string | null;
  startedAt?: string;
  finishedAt?: string;
  durationMs?: number;
  groups?: number;
  products?: number;
  skus?: number;
  hiddenSkus?: number;
  processedItems?: number;
  catalogTotal?: number | null;
  message?: string;
  retryAfterSec?: number;
};
export type GiftCardOption = {
  id: number;
  name: string;
  nameEn?: string | null;
  productTitle?: string | null;
  platform?: string | null;
  region?: string | null;
  costUsd?: string | null;
};
export type AdminUserRow = components['schemas']['AdminUserRow'];
export type PricingSnapshot = components['schemas']['PricingSnapshot'] &
  Partial<components['schemas']['FxRefreshResult']>;
export type SupplierStatus = components['schemas']['SupplierStatus'];
export type SyncResult = components['schemas']['SyncResult'];
export type UploadResult = components['schemas']['UploadResult'];
export type AdminSession = components['schemas']['AdminSession'];
export type AdminAuthUser = components['schemas']['AdminAuthUser'];

export type AdminBanner = {
  id: number;
  src: string;
  mediaType: 'image' | 'gif' | 'video';
  title: string;
  href: string;
  description: string;
  visible: boolean;
  sortOrder: number;
};

export type AdminFlashSaleItem = {
  id: number;
  skuId: number | null;
  image: string;
  originalPrice: number;
  price: number;
  productCode: string;
  productHandle: string;
  productName: string;
  productVariantCode: string;
  productVariantName: string;
  visible: boolean;
  sortOrder: number;
};

export type AdminHomePayload = {
  flashSaleTitle: string;
  flashSaleSubtitle: string;
  flashSaleEndsAt: string | null;
  popularTitle: string;
  popularSubtitle: string;
  introHtml?: string | null;
  banners: AdminBanner[];
  flashSale: AdminFlashSaleItem[];
  popular: AdminProduct[];
  sections?: HomeSectionSetting[];
};

export type HomeSectionSetting = {
  groupId: number;
  slug: string;
  title: string;
  enabled: boolean;
  limit: number;
  productIds: number[];
  products: { id: number; title: string; handle: string }[];
};

export type FazerCardsStatus = {
  provider?: string;
  configured: boolean;
  liveOrderEnabled: boolean;
  webhookConfigured?: boolean;
  host?: string | null;
  keyLast4?: string | null;
  docs?: string;
  steamGiftBlockedWords?: string;
  catalogScopes?: FazerCardsCatalogScope[];
  account?: {
    email: string;
    login?: string;
    plan?: string;
    planExpiresAt?: string;
    planAutoRenew?: boolean;
    wallet: string;
    currency: string;
    active: boolean;
  } | null;
  accountError?: string | null;
  accountErrorCode?: string | null;
};

export type FazerCardsProxyResult = {
  status: number;
  latencyMs: number;
  upstreamPath: string;
  data: Record<string, unknown>;
};

export type FazerCardsAuditRow = {
  id: number;
  userId?: string | null;
  action: string;
  method?: string;
  path?: string;
  ok?: boolean;
  createdAt?: string | null;
};

export type SteamGiftSearchHit = {
  appid: number;
  name: string;
  inShop: boolean;
  visible: boolean;
  productId?: number | null;
};

export type FazerCatalogSearchHit = {
  family: string;
  remoteId: string;
  name: string;
  inShop: boolean;
  visible: boolean;
  productId?: number | null;
  appid?: number;
  platform?: string | null;
};
