/** Paths where Contentsquare must not load the tag (no query string). */
const BLOCKED_PREFIXES = ['/auth', '/profile', '/invoices', '/payments'] as const;

export function hotjarPathOnly(pathname: string): string {
  const base = pathname.split('?')[0]?.split('#')[0]?.trim();
  return base && base.length > 0 ? base : '/';
}

export function isHotjarAllowedPath(pathname: string): boolean {
  const path = hotjarPathOnly(pathname);
  return !BLOCKED_PREFIXES.some((prefix) => path === prefix || path.startsWith(`${prefix}/`));
}

/**
 * Privacy on captured DOM: legacy Hotjar suppress + Contentsquare `data-cs-mask`
 * (https://docs.contentsquare.com/en/web/personal-data-handling/ — Tagging DOM elements;
 * https://support.contentsquare.com/hc/en-us/articles/37271680419345-How-to-suppress-collected-data)
 */
export const hotjarSuppress = { 'data-hj-suppress': '', 'data-cs-mask': '' } as const;

export const HOTJAR_BLOCKED_ROUTE_PREFIXES: readonly string[] = BLOCKED_PREFIXES;
