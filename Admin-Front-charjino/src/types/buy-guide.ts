export type BuyGuideStatus = 'none' | 'draft' | 'published';

export type BuyGuideScopeType = 'category' | 'subcategory' | 'product';

export type BuyGuideSectionType =
  | 'before_buy'
  | 'fields'
  | 'purchase_steps'
  | 'redeem'
  | 'regions'
  | 'errors'
  | 'faq'
  | 'videos'
  | 'sources';

export type BuyGuideMedia = {
  id: number;
  src: string;
  srcset?: string | null;
  width?: number | null;
  height?: number | null;
  bytes?: number | null;
  alt: string;
  kind: 'image' | 'video';
  mime?: string | null;
};

export type BuyGuideSection = {
  type: BuyGuideSectionType;
  inherit?: boolean;
  source?: string;
  data?: Record<string, unknown>;
  notes?: { text: string; tone?: 'info' | 'warning' }[];
  items?: unknown[];
  steps?: unknown[];
  tabs?: unknown[];
  rows?: unknown[];
  media?: BuyGuideMedia | null;
};

export type AdminBuyGuide = {
  id: number;
  scopeType: BuyGuideScopeType;
  scopeKey: string;
  category: string;
  subcategory?: string | null;
  productId?: number | null;
  productHandle?: string | null;
  title?: string | null;
  summary?: string | null;
  intro?: string | null;
  status: 'draft' | 'published';
  lastReviewedAt?: string | null;
  seoTitle?: string | null;
  seoDescription?: string | null;
  publishedAt?: string | null;
  updatedAt?: string | null;
  sections: BuyGuideSection[];
};

export type BuyGuideCoverageChild = {
  subcategory: string;
  label: string;
  productCount: number;
  status: BuyGuideStatus;
  lastReviewedAt?: string | null;
  hasOwnIntro: boolean;
  overrideCount: number;
  guideId?: number | null;
  duplicateRisk: boolean;
};

export type BuyGuideCoverageNode = {
  category: string;
  label: string;
  productCount: number;
  status: BuyGuideStatus;
  lastReviewedAt?: string | null;
  hasOwnIntro: boolean;
  overrideCount: number;
  guideId?: number | null;
  duplicateRisk: boolean;
  children: BuyGuideCoverageChild[];
};
