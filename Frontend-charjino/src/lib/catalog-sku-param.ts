type SkuRef = { id: number };

export function parseSkuIdParam(raw: string | null | undefined): number | null {
  const value = String(raw ?? '').trim();
  if (!/^\d+$/.test(value)) {
    return null;
  }
  const id = Number(value);
  if (!Number.isInteger(id) || id < 1) {
    return null;
  }
  return id;
}

export function preferredSkuId<T extends SkuRef>(skus: T[], requested: number | null | undefined): number | null {
  if (requested == null) {
    return null;
  }
  return skus.some((sku) => sku.id === requested) ? requested : null;
}

export function resolveSelectedSkuId<T extends SkuRef>(
  skus: T[],
  listedSkus: T[],
  requested: number | null | undefined,
): number | null {
  return preferredSkuId(skus, requested) ?? listedSkus[0]?.id ?? skus[0]?.id ?? null;
}
