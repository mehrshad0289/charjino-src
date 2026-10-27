import { notFound, permanentRedirect } from 'next/navigation';

type CatalogFound<T> = { ok: true; data: T };
type CatalogMissing = { ok: false; message: string; notFound?: boolean; redirectTo?: string };

export function unwrapCatalogRecord<T>(result: CatalogFound<T> | CatalogMissing): T {
  if (!result.ok && result.redirectTo) {
    permanentRedirect(result.redirectTo);
  }
  if (result.ok) {
    return result.data;
  }
  notFound();
}
