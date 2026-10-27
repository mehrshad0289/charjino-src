export type BuyGuideMedia = {
  id?: number;
  src: string;
  srcset?: string | null;
  width?: number | null;
  height?: number | null;
  alt: string;
  kind?: 'image' | 'video';
};

export type BuyGuideNote = { text: string; tone?: 'info' | 'warning' };
export type BuyGuideField = { name?: string; label: string; help?: string; media?: BuyGuideMedia | null };
export type BuyGuideStep = { text: string; media?: BuyGuideMedia | null; alt?: string };
export type BuyGuideRedeemTab = { key: string; label: string; steps: BuyGuideStep[]; media?: BuyGuideMedia | null };
export type BuyGuideRegion = {
  code?: string;
  label: string;
  compat?: string;
  product_handle?: string;
  current?: boolean;
};
export type BuyGuideError = { error: string; fix: string };
export type BuyGuideFaq = { q: string; a: string };
export type BuyGuideVideo = {
  provider: 'aparat' | 'youtube' | 'self';
  url?: string;
  title?: string;
  lang?: 'fa' | 'en';
  duration?: string;
  thumbnail?: BuyGuideMedia | null;
  video?: BuyGuideMedia | null;
};
export type BuyGuideSource = { url: string; label?: string };

export type BuyGuideSection = {
  type: string;
  source?: string;
  notes?: BuyGuideNote[];
  items?: Array<BuyGuideField | BuyGuideError | BuyGuideFaq | BuyGuideVideo | BuyGuideSource | Record<string, unknown>>;
  steps?: BuyGuideStep[];
  tabs?: BuyGuideRedeemTab[];
  rows?: BuyGuideRegion[];
};

export type BuyGuide = {
  title?: string | null;
  summary?: string | null;
  introHtml?: string | null;
  lastReviewedAt?: string | null;
  scope?: { category?: string | null; subcategory?: string | null; productId?: number | null };
  seo?: { title?: string | null; description?: string | null };
  sections?: BuyGuideSection[];
  legacyItems?: import('@/lib/purchase-guide').PurchaseGuideItem[];
};
