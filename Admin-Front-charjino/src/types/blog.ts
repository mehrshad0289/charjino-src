export type AdminBlogCategory = {
  id: number;
  title: string;
  slug: string;
  description?: string | null;
  bodyHtml?: string | null;
  metaTitle?: string | null;
  metaDescription?: string | null;
  noindex?: boolean;
  image?: string | null;
  imageAlt?: string | null;
  parentId?: number | null;
  parentTitle?: string | null;
  isActive: boolean;
  sortOrder: number;
  updatedAt?: string | null;
};

export type AdminBlogTag = {
  id: number;
  title: string;
  slug: string;
  description?: string | null;
  bodyHtml?: string | null;
  image?: string | null;
  imageAlt?: string | null;
  metaTitle?: string | null;
  metaDescription?: string | null;
  forceNoindex?: boolean;
  noindex: boolean;
  postsCount?: number;
  updatedAt?: string | null;
};

export type AdminBlogFaq = {
  question: string;
  answer: string;
};

export type AdminBlogRevision = {
  id: number;
  createdAt?: string | null;
  title?: string | null;
};

export type AdminBlogPost = {
  id: number;
  title: string;
  slug: string;
  excerpt?: string | null;
  content?: string;
  cover?: string | null;
  coverAlt?: string | null;
  coverWidth?: number | null;
  coverHeight?: number | null;
  categoryId?: number | null;
  categoryTitle?: string | null;
  userId?: string | null;
  authorName?: string | null;
  status: 'draft' | 'published' | 'scheduled';
  publishedAt?: string | null;
  metaTitle?: string | null;
  metaDescription?: string | null;
  canonicalUrl?: string | null;
  noindex?: boolean;
  focusKeyword?: string | null;
  ogTitle?: string | null;
  ogDescription?: string | null;
  ogImage?: string | null;
  twitterTitle?: string | null;
  twitterDescription?: string | null;
  twitterImage?: string | null;
  secondaryKeywords?: string[];
  faq?: AdminBlogFaq[];
  previewToken?: string | null;
  tagIds?: number[];
  tags?: { id: number; title: string; slug: string }[];
  productIds?: number[];
  viewsCount: number;
  likesCount: number;
  commentsCount: number;
  readingMinutes?: number;
  updatedAt?: string | null;
  revisions?: AdminBlogRevision[];
};

export type AdminBlogComment = {
  id: number;
  body: string;
  status: 'pending' | 'approved' | 'hidden';
  parentId?: number | null;
  likesCount: number;
  createdAt?: string | null;
  authorName?: string | null;
  postId: number;
  postTitle?: string | null;
  postSlug?: string | null;
};

export type AdminPageMeta = {
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};

export type BlogLinkHit = {
  id?: number;
  title: string;
  href: string;
  slug?: string;
  handle?: string;
};
