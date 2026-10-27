import { NextResponse } from 'next/server';
import { apiPaths } from '@/lib/api-paths';
import { ApiError, serverFetch } from '@/lib/server-fetcher';

export const dynamic = 'force-dynamic';

type LivePrices = {
  minPrice: number | null;
  skus: Array<{
    id: number;
    code: string;
    price: number;
    originalPrice: number | null;
    saleEndsAt: string | null;
  }>;
};

export async function GET(_request: Request, context: { params: Promise<{ handle: string }> }) {
  const { handle } = await context.params;
  const normalized = handle?.trim() ?? '';
  if (!normalized) {
    return NextResponse.json({ success: false, message: 'شناسه محصول نامعتبر است' }, { status: 400 });
  }

  try {
    const data = await serverFetch<LivePrices>(apiPaths.catalogProductPrices(normalized), { auth: false });
    return NextResponse.json(
      { success: true, data },
      { headers: { 'Cache-Control': 'private, no-store' } },
    );
  } catch (error) {
    const status = error instanceof ApiError ? error.statusCode || 502 : 502;
    const message = error instanceof Error ? error.message : 'قیمت در دسترس نیست';
    return NextResponse.json({ success: false, message }, { status });
  }
}
