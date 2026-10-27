import { NextResponse } from 'next/server';
import { getBackendUrl } from '@/lib/env';

export const runtime = 'nodejs';
export const dynamic = 'force-dynamic';

export async function GET(request: Request) {
  const incoming = new URL(request.url);
  const q = (incoming.searchParams.get('q') ?? '').slice(0, 100);
  const page = incoming.searchParams.get('page') ?? '1';
  const perPage =
    incoming.searchParams.get('per_page') ?? incoming.searchParams.get('perPage') ?? '10';
  const mode = incoming.searchParams.get('mode') ?? 'auto';
  const search = new URLSearchParams({ q, page, per_page: perPage, mode });
  for (const key of ['group', 'platform', 'region'] as const) {
    const value = incoming.searchParams.get(key)?.trim();
    if (value) search.set(key, value);
  }

  const controller = new AbortController();
  // artisan serve on Windows is single-threaded; steam-cover/catalog can occupy PHP for >4s.
  const timer = setTimeout(() => controller.abort(), 15_000);
  if (request.signal.aborted) {
    controller.abort();
  } else {
    request.signal.addEventListener('abort', () => controller.abort(), { once: true });
  }
  let response: Response;
  try {
    response = await fetch(`${getBackendUrl()}/catalog/smart-search?${search}`, {
      headers: { Accept: 'application/json' },
      cache: 'no-store',
      signal: controller.signal,
    });
  } catch {
    clearTimeout(timer);
    return NextResponse.json(
      { success: false, message: 'اتصال به سرور برقرار نشد' },
      { status: 503 },
    );
  }
  clearTimeout(timer);

  const payload = await response.json().catch(() => ({
    success: false,
    message: 'پاسخ سرچ نامعتبر بود',
  }));

  const headers = new Headers();
  const retryAfter = response.headers.get('Retry-After');
  if (retryAfter) headers.set('Retry-After', retryAfter);

  return NextResponse.json(payload, { status: response.status, headers });
}
