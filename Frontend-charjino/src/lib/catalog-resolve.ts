import { apiPaths } from '@/lib/api-paths';

function backendBase(): string {
  const raw = process.env.BACKEND_URL?.trim() || process.env.NEXT_PUBLIC_BACKEND_URL?.trim() || '';
  if (!raw) {
    throw new Error('BACKEND_URL is not set');
  }
  return raw.replace(/\/+$/, '');
}

export type CatalogResolveStatus = 'ok' | 'redirect' | 'gone' | 'missing';

export type CatalogResolveResult = {
  status: CatalogResolveStatus;
  target: string | null;
};

const memory = new Map<string, { at: number; ttl: number; value: CatalogResolveResult }>();
const REDIRECT_TTL_MS = 6 * 60 * 60 * 1000;
const LIVE_TTL_MS = 60 * 1000;

function fallback(): CatalogResolveResult {
  return { status: 'missing', target: null };
}

export async function resolveCatalogPath(path: string): Promise<CatalogResolveResult> {
  const normalized = path.trim() || '/';
  const cached = memory.get(normalized);
  if (cached && Date.now() - cached.at < cached.ttl) {
    return cached.value;
  }
  try {
    const url = `${backendBase()}${apiPaths.catalogResolve(normalized)}`;
    const response = await fetch(url, {
      ...(process.env.NEXT_RUNTIME === 'edge' || process.env.NEXT_PHASE === 'phase-production-build'
        ? { cache: 'no-store' as const }
        : { next: { revalidate: 60 } }),
      headers: { Accept: 'application/json' },
      signal: AbortSignal.timeout(1500),
    });
    const payload = (await response.json()) as {
      success?: boolean;
      data?: { status?: string; target?: string | null };
    };
    const status = payload.data?.status;
    const value: CatalogResolveResult = {
      status: status === 'ok' || status === 'redirect' || status === 'gone' || status === 'missing' ? status : 'missing',
      target: payload.data?.target?.trim() || null,
    };
    memory.set(normalized, {
      at: Date.now(),
      ttl: value.status === 'redirect' || value.status === 'gone' ? REDIRECT_TTL_MS : LIVE_TTL_MS,
      value,
    });
    return value;
  } catch {
    return fallback();
  }
}

export async function resolvedInternalHref(target: string): Promise<string> {
  if (target.startsWith('/') && !target.startsWith('/catalog/')) {
    return target;
  }
  if (target.startsWith('/catalog/category')) {
    return target;
  }
  const path = target.startsWith('/') ? target : `/catalog/${target}`;
  const resolved = await resolveCatalogPath(path);
  if (resolved.status === 'redirect' && resolved.target) {
    return resolved.target;
  }
  return path;
}
