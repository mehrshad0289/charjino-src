import type { components } from '@/types/api.gen';

export type ApiResponse<T> = Omit<components['schemas']['SuccessEnvelope'], 'data'> & {
  data?: T;
};

export type ApiErrorResponse = components['schemas']['ErrorResponse'];

export type CatalogHome = components['schemas']['CatalogHome'] & {
  flashSaleTitle?: string;
  flashSaleSubtitle?: string;
  flashSaleEndsAt?: string | null;
  popularTitle?: string;
  popularSubtitle?: string;
  introHtml?: string | null;
};
export type HomeGroup = components['schemas']['HomeGroup'];
export type PublicProduct = components['schemas']['PublicProduct'] & {
  platform?: string | null;
  region?: string | null;
  brand?: string | null;
  shortDescription?: string | null;
  regionRestriction?: boolean;
  fulfillmentType?: string | null;
  indexable?: boolean;
  updatedAt?: string | null;
  searchTags?: string[];
  descriptionHtml?: string | null;
  purchaseSchema?: PurchaseSchema | null;
  regionNote?: string | null;
  regionAvailability?: { available?: string[]; unavailable?: string[]; region_type?: string } | null;
  available?: boolean;
  temporarilyUnavailable?: boolean;
  isOrderable?: boolean;
  seoTitle?: string | null;
  seoDescription?: string | null;
};
export type PublicProductDetail = components['schemas']['PublicProductDetail'] & {
  platform?: string | null;
  region?: string | null;
  fulfillmentType?: string | null;
  searchTags?: string[];
  category?: {
    id: number;
    slug: string;
    title: string;
    purchaseGuide?: string | null;
    purchaseGuideItems?: import('@/lib/purchase-guide').PurchaseGuideItem[];
  } | null;
  buyGuide?: import('@/types/buy-guide').BuyGuide | null;
};
export type PackFilterOption = { value: string; label: string };
export type PackFilter = {
  key: 'region' | 'edition' | 'denomination' | 'duration' | string;
  label: string;
  options: PackFilterOption[];
};
export type PublicSku = Omit<components['schemas']['PublicSku'], 'costUsd' | 'liveCostUsd'> & {
  region?: string | null;
  minOrderQuantity?: number;
  maxOrderQuantity?: number;
  stock?: number | null;
  nameEn?: string | null;
  filters?: Record<string, string>;
};
export type PublicCategory = components['schemas']['PublicCategory'] & {
  purchaseGuide?: string | null;
  purchaseGuideItems?: import('@/lib/purchase-guide').PurchaseGuideItem[];
  updatedAt?: string | null;
  seoTitle?: string | null;
  seoDescription?: string | null;
};
export type PublicCategoryPage = components['schemas']['PublicCategoryPage'];
export type PublicProductPage = components['schemas']['PublicProductPage'];
export type PageMeta = components['schemas']['PageMeta'] & {
  platforms?: string[];
  regions?: string[];
};
export type PurchaseSchemaField = {
  key?: string;
  name?: string;
  label?: string;
  labelFa?: string;
  help?: string | null;
  type?: string;
  options?: unknown;
  required?: boolean;
  pattern?: string | null;
};

export type PurchaseSchema = {
  type?: string;
  fields?: PurchaseSchemaField[];
  quantity?: { enabled?: boolean; min?: number; max?: number };
  amount?: {
    enabled?: boolean;
    currencies?: string[];
    minUsd?: string;
    maxUsd?: string;
    decimals?: Record<string, number>;
  };
  validation?: { mode?: string; validateCategoryId?: string | null };
  regionNote?: string | null;
  regionNoteEn?: string | null;
};

export type CatalogInputField = components['schemas']['BuyerInputField'] & {
  help?: string | null;
  pattern?: string | null;
};
export type CodmRegionPayload = {
  status?: 'match' | 'mismatch' | 'unknown' | string | null;
  variantCountry?: string | null;
  nickname?: string | null;
  shortId?: string | null;
  accountCountry?: string | null;
  suggestedVariant?: string | null;
  suggestedHandle?: string | null;
  variantHandle?: string | null;
};

export type ValidatePlayerResult = components['schemas']['ValidatePlayerResult'] & {
  validateMode?: 'api' | 'manual' | 'codm_store';
  validation?: 'ok' | 'unsupported' | 'skipped' | 'unverified';
  warning?: string | null;
  identityKind?: 'player' | 'user' | 'account';
  unverified?: boolean;
  codmRegion?: CodmRegionPayload | null;
};

export type CatalogProduct = PublicProduct &
  Partial<Pick<PublicProductDetail, 'skus' | 'requirements' | 'inputFields' | 'category' | 'buyGuide'>> & {
    validateMode?: 'api' | 'manual';
    identityKind?: 'player' | 'user' | 'account';
    giftProxy?: boolean;
    purchaseSchema?: PurchaseSchema | null;
    regionNote?: string | null;
    regionAvailability?: { available?: string[]; unavailable?: string[]; region_type?: string } | null;
    packFilters?: PackFilter[];
    similar?: PublicProduct[];
    alternate?: PublicProduct | null;
    available?: boolean;
    temporarilyUnavailable?: boolean;
    isOrderable?: boolean;
    codm?: {
      hint: string;
      versions: { handle: string; label: string; selected: boolean }[];
      regionCheck?: boolean;
      variantCountry?: string | null;
      guideHref?: string | null;
    } | null;
  };
export type Health = components['schemas']['Health'];
export type CustomerOrder = components['schemas']['CustomerOrder'] & {
  subtotalToman?: number;
  discountToman?: number;
  couponCode?: string | null;
  playerId?: string | null;
  versionLabel?: string | null;
  statusLabelFa?: string;
  fulfillmentType?: string | null;
  requirementsMasked?: Array<{ key: string; label: string; value: string }>;
  failMessageFa?: string | null;
  pollHintSeconds?: number | null;
  delivery?: { kind?: string | null; itemCount?: number; revealedOnce?: boolean; summary?: string | null };
  refund?: { method?: string; status?: string } | null;
  guide?: { title?: string; url?: string } | null;
};
export type CustomerOrderPage = components['schemas']['CustomerOrderPage'];
export type PayOrderResult = components['schemas']['PayOrderResult'];
export type ShopSession = components['schemas']['ShopSession'];
export type ShopAuthUser = components['schemas']['ShopAuthUser'] & {
  name?: string | null;
};
export type HomeBanner = components['schemas']['Banner'] & {
  mediaType?: 'image' | 'gif' | 'video';
};
export type FlashSaleItem = components['schemas']['FlashSaleItem'];

export type CatalogGroup = HomeGroup;
export type CatalogSku = PublicSku;
export type CatalogNavCategory = Pick<PublicCategory, 'slug' | 'title'>;
