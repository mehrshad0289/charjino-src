export type BlogCategory = {
  id: number;
  title: string;
  slug: string;
  description?: string | null;
  bodyHtml?: string | null;
  image?: string | null;
  imageAlt?: string | null;
  noindex?: boolean;
  postsCount?: number;
  children?: { id: number; title: string; slug: string }[];
};

export type BlogTag = {
  id: number;
  title: string;
  slug: string;
  description?: string | null;
  bodyHtml?: string | null;
  image?: string | null;
  noindex?: boolean;
  postsCount?: number;
};

export type BlogAuthor = {
  name: string;
  slug?: string | null;
  postsCount?: number;
};

export type BlogSeo = {
  title: string;
  description: string;
  noindex: boolean;
};

export type BlogFaqItem = {
  question: string;
  answer: string;
};

export type BlogPostCard = {
  id: number;
  title: string;
  slug: string;
  excerpt?: string | null;
  cover?: string | null;
  coverAlt?: string | null;
  publishedAt?: string | null;
  updatedAt?: string | null;
  viewsCount: number;
  likesCount: number;
  commentsCount: number;
  readingMinutes?: number;
  liked?: boolean;
  category?: { id: number; title: string; slug: string } | null;
  author?: BlogAuthor | null;
};

export type BlogRelatedProduct = {
  id: number;
  handle: string;
  title: string;
  image?: string | null;
};

export type BlogPost = BlogPostCard & {
  content: string;
  metaTitle?: string | null;
  metaDescription?: string | null;
  canonicalUrl?: string | null;
  noindex?: boolean;
  faq?: BlogFaqItem[];
  ogTitle?: string | null;
  ogDescription?: string | null;
  ogImage?: string | null;
  twitterTitle?: string | null;
  twitterDescription?: string | null;
  twitterImage?: string | null;
  secondaryKeywords?: string[];
  tags?: { title: string; slug: string }[];
  coverWidth?: number | null;
  coverHeight?: number | null;
};

export type BlogComment = {
  id: number;
  body: string;
  status?: 'pending' | 'approved' | 'hidden';
  parentId?: number | null;
  likesCount: number;
  liked?: boolean;
  createdAt?: string | null;
  author?: { name: string };
  replies?: BlogComment[];
};

export type BlogPageMeta = {
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};
