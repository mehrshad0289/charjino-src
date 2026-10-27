export type AdminProductComment = {
  id: number;
  body: string;
  status: 'pending' | 'approved' | 'hidden';
  parentId?: number | null;
  likesCount: number;
  createdAt?: string | null;
  authorName?: string | null;
  productId: number;
  productTitle?: string | null;
  productHandle?: string | null;
};

export type AdminPageMeta = {
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};
