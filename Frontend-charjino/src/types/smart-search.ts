export type SmartSearchMode = 'suggest' | 'entities' | 'products' | 'empty';

export type SmartSearchReferenceType = 'group' | 'platform' | 'brand' | 'category' | 'tag';

export type SmartSearchReference = {
  result_type: SmartSearchReferenceType;
  name: string;
  slug: string;
  href: string;
  name_en?: string | null;
  group?: string | null;
  active_products_count?: number;
};

export type SmartSearchProduct = {
  result_type: 'product';
  id?: number;
  handle: string;
  title: string;
  title_en?: string | null;
  thumbnail?: string | null;
  categoryName?: string | null;
  group?: string | null;
  platform?: string | null;
  product_type?: string | null;
  matched_package_label?: string | null;
  minPrice?: number | null;
  href: string;
};

export type SmartSearchItem = SmartSearchReference | SmartSearchProduct;

export type SmartSearchResponse = {
  mode: SmartSearchMode;
  query: string;
  items: SmartSearchItem[];
  pagination: {
    current_page: number;
    last_page: number;
    has_more_pages: boolean;
  };
};

export function isSmartSearchProduct(item: SmartSearchItem): item is SmartSearchProduct {
  return item.result_type === 'product';
}

export function isSuggestMode(mode: SmartSearchMode): boolean {
  return mode === 'suggest' || mode === 'entities';
}
