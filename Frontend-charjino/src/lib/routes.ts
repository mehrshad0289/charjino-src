export const ROUTES = {
  home: '/',
  auth: '/auth',
  catalog: (handle: string) => `/catalog/${handle}`,
  categories: '/catalog/category',
  category: (slug: string) => `/catalog/category/${slug}`,
  invoices: '/invoices',
  invoice: (id: string) => `/invoices/${id}`,
  profile: '/profile',
  profileOrders: '/profile/orders',
  profileOrder: (id: string) => `/profile/orders/${id}`,
  profileTickets: '/profile/tickets',
  profileTicketNew: '/profile/tickets/new',
  profileTicket: (id: string) => `/profile/tickets/${id}`,
  profileFavorites: '/profile/favorites',
  profileAccount: '/profile/account',
  profileWallet: '/profile/wallet',
  zibalReturn: '/payments/zibal',
  zarinpalReturn: '/payments/zarinpal',
  leaderboard: '/leaderboard',
  privacy: '/privacy',
  terms: '/terms',
  about: '/about',
  contact: '/contact',
  uncredited: '/uncredited',
  figma: '/figma',
  search: '/search',
  blog: '/blog',
  blogSearch: '/blog/search',
  blogCategory: (slug: string) => `/blog/category/${encodeURIComponent(slug)}`,
  blogTag: (slug: string) => `/blog/tag/${encodeURIComponent(slug)}`,
  blogAuthor: (slug: string) => `/blog/author/${encodeURIComponent(slug)}`,
  blogPost: (slug: string) => `/blog/${encodeURIComponent(slug)}`,
  blogFeed: '/blog/feed.xml',
} as const;

export function catalogBrowseHref(params: {
  category?: string | null;
  q?: string | null;
  platform?: string | null;
  region?: string | null;
  page?: number | null;
  sort?: string | null;
} = {}) {
  const search = new URLSearchParams();
  const category = params.category?.trim();
  const q = params.q?.trim();
  const platform = params.platform?.trim();
  const region = params.region?.trim();
  const sort = params.sort?.trim();
  if (q) search.set('q', q);
  if (platform) search.set('platform', platform);
  if (region) search.set('region', region);
  if (sort && sort !== 'listed') search.set('sort', sort);
  if (params.page && params.page > 1) search.set('page', String(params.page));
  const qs = search.toString();
  const path = category ? ROUTES.category(category) : ROUTES.categories;
  return qs ? `${path}?${qs}` : path;
}
