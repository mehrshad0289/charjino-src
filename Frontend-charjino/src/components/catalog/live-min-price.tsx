'use client';

import { useEffect, useState } from 'react';
import { fetchLiveCatalogPrices } from '@/lib/catalog-live-price';
import { formatFromToman } from '@/lib/price';

export function LiveMinPrice({
  handle,
  initialLabel,
  initialMinPrice,
}: {
  handle: string;
  initialLabel: string;
  initialMinPrice?: number | null;
}) {
  const [label, setLabel] = useState(initialLabel);

  useEffect(() => {
    let cancelled = false;
    void fetchLiveCatalogPrices(handle).then((live) => {
      if (cancelled || live?.minPrice == null || live.minPrice === initialMinPrice) {
        return;
      }
      const next = formatFromToman(live.minPrice);
      if (next) {
        setLabel(next);
      }
    });
    return () => {
      cancelled = true;
    };
  }, [handle, initialMinPrice]);

  return <>{label}</>;
}
