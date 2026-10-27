'use client';

import Link from 'next/link';
import type { MouseEvent } from 'react';
import { toFaDigits } from '@/lib/fa';

type CatalogPaginationProps = {
  page: number;
  totalPages: number;
  hrefForPage: (page: number) => string;
  onPage?: (href: string) => void;
};

const pageBtnClass =
  'inline-flex min-w-9 items-center justify-center rounded-md border border-border bg-card px-3 py-2 text-sm font-medium text-text shadow-card transition-all duration-300 ease-out hover:bg-card-muted';

const pageBtnActiveClass =
  'inline-flex min-w-9 items-center justify-center rounded-md border border-primary-600 bg-primary-600 px-3 py-2 text-sm font-medium text-white shadow-card';

function pageItems(current: number, total: number): Array<number | 'gap'> {
  if (total <= 7) {
    return Array.from({ length: total }, (_, index) => index + 1);
  }
  const unique = [...new Set([1, 2, current - 1, current, current + 1, total - 1, total])]
    .filter((page) => page >= 1 && page <= total)
    .sort((left, right) => left - right);
  const items: Array<number | 'gap'> = [];
  unique.forEach((page, index) => {
    if (index > 0 && page - (unique[index - 1] ?? page) > 1) {
      items.push('gap');
    }
    items.push(page);
  });
  return items;
}

function PageHref({
  href,
  className,
  current,
  children,
  onPage,
}: {
  href: string;
  className: string;
  current?: boolean;
  children: string;
  onPage?: (href: string) => void;
}) {
  function onClick(event: MouseEvent<HTMLAnchorElement>) {
    if (!onPage) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();
    onPage(href);
  }

  return (
    <Link href={href} className={className} aria-current={current ? 'page' : undefined} onClick={onClick}>
      {children}
    </Link>
  );
}

export function CatalogPagination({ page, totalPages, hrefForPage, onPage }: CatalogPaginationProps) {
  const safeTotal = Math.max(1, totalPages);
  const safePage = Math.min(Math.max(1, page), safeTotal);
  if (safeTotal <= 1) return null;
  const hasPrev = safePage > 1;
  const hasNext = safePage < safeTotal;

  return (
    <nav className="hidden items-center justify-center gap-2 text-sm lg:flex" aria-label="صفحه‌بندی">
      {hasPrev ? (
        <PageHref href={hrefForPage(safePage - 1)} className={pageBtnClass} onPage={onPage}>
          قبلی
        </PageHref>
      ) : (
        <span className="px-3 py-2 text-text/25">قبلی</span>
      )}
      {pageItems(safePage, safeTotal).map((item, index) =>
        item === 'gap' ? (
          <span key={`gap-${index}`} className="px-1 text-text/40">
            …
          </span>
        ) : (
          <PageHref
            key={item}
            href={hrefForPage(item)}
            current={item === safePage}
            className={item === safePage ? pageBtnActiveClass : pageBtnClass}
            onPage={onPage}
          >
            {toFaDigits(item)}
          </PageHref>
        ),
      )}
      {hasNext ? (
        <PageHref href={hrefForPage(safePage + 1)} className={pageBtnClass} onPage={onPage}>
          بعدی
        </PageHref>
      ) : (
        <span className="px-3 py-2 text-text/25">بعدی</span>
      )}
    </nav>
  );
}
