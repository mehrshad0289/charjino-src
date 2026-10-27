export const apiPaths = {
  health: '/health',
  requestOtp: '/auth/request-otp',
  verifyOtp: '/auth/verify-otp',
  refresh: '/auth/refresh',
  logout: '/auth/logout',
  me: '/auth/me',
  authProfile: '/auth/profile',
  catalogHome: '/catalog/home',
  catalogCategories: '/catalog/categories',
  catalogCategory: (slug: string, page?: number) => {
    const search = new URLSearchParams();
    if (page && page > 1) search.set('page', String(page));
    const qs = search.toString();
    return `/catalog/categories/${encodeURIComponent(slug)}${qs ? `?${qs}` : ''}`;
  },
  catalogProducts: '/catalog/products',
  catalogResolve: (path: string) => `/catalog/resolve?path=${encodeURIComponent(path)}`,
  catalogProduct: (handle: string) => `/catalog/products/${encodeURIComponent(handle)}`,
  catalogProductPrices: (handle: string) => `/catalog/products/${encodeURIComponent(handle)}/prices`,
  catalogProductComments: (handle: string, page?: number) => {
    const search = new URLSearchParams();
    if (page && page > 1) search.set('page', String(page));
    const qs = search.toString();
    const path = `/catalog/products/${encodeURIComponent(handle)}/comments`;
    return qs ? `${path}?${qs}` : path;
  },
  catalogCommentLike: (id: number) => `/catalog/comments/${id}/like`,
  blogHub: (page?: number) => (page && page > 1 ? `/blog?page=${page}` : '/blog'),
  blogPosts: (params: { q?: string; category?: string; page?: number; perPage?: number } = {}) => {
    const search = new URLSearchParams();
    if (params.q?.trim()) search.set('q', params.q.trim().slice(0, 100));
    if (params.category?.trim()) search.set('category', params.category.trim());
    if (params.page && params.page > 1) search.set('page', String(params.page));
    if (params.perPage && params.perPage > 0) search.set('perPage', String(params.perPage));
    const qs = search.toString();
    return qs ? `/blog/posts?${qs}` : '/blog/posts';
  },
  blogPost: (slug: string) => `/blog/posts/${encodeURIComponent(slug)}`,
  blogPreview: (token: string) => `/blog/preview/${encodeURIComponent(token)}`,
  blogPostComments: (slug: string, page?: number) => {
    const search = new URLSearchParams();
    if (page && page > 1) search.set('page', String(page));
    const qs = search.toString();
    return qs ? `/blog/posts/${encodeURIComponent(slug)}/comments?${qs}` : `/blog/posts/${encodeURIComponent(slug)}/comments`;
  },
  blogPostLike: (slug: string) => `/blog/posts/${encodeURIComponent(slug)}/like`,
  blogCommentLike: (id: number) => `/blog/comments/${id}/like`,
  blogCategories: '/blog/categories',
  blogCategory: (slug: string, page?: number) => {
    const search = new URLSearchParams();
    if (page && page > 1) search.set('page', String(page));
    const qs = search.toString();
    return `/blog/categories/${encodeURIComponent(slug)}${qs ? `?${qs}` : ''}`;
  },
  blogTag: (slug: string, page?: number) => {
    const search = new URLSearchParams();
    if (page && page > 1) search.set('page', String(page));
    const qs = search.toString();
    return `/blog/tags/${encodeURIComponent(slug)}${qs ? `?${qs}` : ''}`;
  },
  blogAuthor: (slug: string, page?: number) => {
    const search = new URLSearchParams();
    if (page && page > 1) search.set('page', String(page));
    const qs = search.toString();
    return `/blog/authors/${encodeURIComponent(slug)}${qs ? `?${qs}` : ''}`;
  },
  blogSitemap: '/blog/sitemap',
  catalogSmartSearch: (params: {
    q: string;
    page?: number;
    perPage?: number;
    mode?: string;
    group?: string;
    platform?: string;
    region?: string;
  }) => {
    const search = new URLSearchParams();
    search.set('q', params.q.slice(0, 100));
    search.set('mode', params.mode ?? 'products');
    if (params.page && params.page > 1) search.set('page', String(params.page));
    if (params.perPage && params.perPage > 0) search.set('per_page', String(params.perPage));
    if (params.group) search.set('group', params.group);
    if (params.platform) search.set('platform', params.platform);
    if (params.region) search.set('region', params.region);
    return `/catalog/smart-search?${search.toString()}`;
  },
  catalogProductsQuery: (
    params: {
      q?: string;
      popular?: boolean;
      category?: string;
      page?: number;
      perPage?: number;
      platform?: string;
      region?: string;
      sort?: string;
    } = {},
  ) => {
    const search = new URLSearchParams();
    const q = params.q?.trim();
    const category = params.category?.trim();
    const platform = params.platform?.trim();
    const region = params.region?.trim();
    if (q) search.set('q', q);
    if (category) search.set('category', category);
    if (platform) search.set('platform', platform);
    if (region) search.set('region', region);
    if (params.popular) search.set('popular', '1');
    if (params.page && params.page > 0) search.set('page', String(params.page));
    if (params.perPage && params.perPage > 0) search.set('perPage', String(params.perPage));
    if (params.sort && params.sort !== 'listed') search.set('sort', params.sort);
    const qs = search.toString();
    return qs ? `/catalog/products?${qs}` : '/catalog/products';
  },
  checkoutValidatePlayer: '/checkout/validate-player',
  checkoutCoupon: '/checkout/coupon',
  orders: '/orders',
  ordersQuery: (page = 1) => {
    const search = new URLSearchParams();
    if (page > 1) search.set('page', String(page));
    const qs = search.toString();
    return qs ? `/orders?${qs}` : '/orders';
  },
  order: (number: string) => `/orders/${encodeURIComponent(number)}`,
  payOrder: (number: string) => `/orders/${encodeURIComponent(number)}/pay`,
  revealOrder: (number: string) => `/orders/${encodeURIComponent(number)}/reveal`,
  refreshOrder: (number: string) => `/orders/${encodeURIComponent(number)}/refresh`,
  meWallet: '/me/wallet',
  steamTopupQuote: (currency: string, amount: string) =>
    `/catalog/steam-topup/quote?currency=${encodeURIComponent(currency)}&amount=${encodeURIComponent(amount)}`,
  tickets: '/tickets',
  ticketsQuery: (page = 1) => {
    const search = new URLSearchParams();
    if (page > 1) search.set('page', String(page));
    const qs = search.toString();
    return qs ? `/tickets?${qs}` : '/tickets';
  },
  ticket: (id: string) => `/tickets/${encodeURIComponent(id)}`,
  ticketMessages: (id: string) => `/tickets/${encodeURIComponent(id)}/messages`,
  favorites: '/favorites',
  favoritesQuery: (params: { page?: number; productId?: number; idsOnly?: boolean } = {}) => {
    const search = new URLSearchParams();
    if (params.productId && params.productId > 0) search.set('productId', String(params.productId));
    if (params.page && params.page > 1) search.set('page', String(params.page));
    if (params.idsOnly) search.set('idsOnly', '1');
    const qs = search.toString();
    return qs ? `/favorites?${qs}` : '/favorites';
  },
  favorite: (productId: number) => `/favorites/${productId}`,
  paymentCallbackMock: '/payments/callback/mock',
  paymentVerifyZibal: '/payments/verify/zibal',
  paymentVerifyZarinpal: '/payments/verify/zarinpal',
} as const;
