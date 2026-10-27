'use client';

import { usePathname, useSearchParams } from 'next/navigation';
import { useEffect } from 'react';
import { sanitizeGaPageLocation } from '@/lib/ga-page-location';

type Gtag = (...args: unknown[]) => void;

function applySanitizedLocation() {
  const gtag = (window as Window & { gtag?: Gtag }).gtag;
  if (typeof gtag !== 'function') return;
  gtag('set', {
    anonymize_ip: true,
    page_location: sanitizeGaPageLocation(window.location.href),
  });
}

export function GaPrivacy() {
  const pathname = usePathname();
  const searchParams = useSearchParams();

  useEffect(() => {
    applySanitizedLocation();
  }, [pathname, searchParams]);

  return null;
}
