import Link from 'next/link';
import { toFaDigits } from '@/lib/fa';

type BlogPagerProps = {
  page: number;
  totalPages: number;
  hrefFor: (page: number) => string;
};

export function BlogPager({ page, totalPages, hrefFor }: BlogPagerProps) {
  if (totalPages <= 1) return null;
  const prev = page > 1 ? hrefFor(page - 1) : null;
  const next = page < totalPages ? hrefFor(page + 1) : null;
  return (
    <nav className="flex items-center justify-between gap-3 text-sm" aria-label="صفحه‌بندی">
      {prev ? (
        <Link href={prev} className="text-primary-600">
          صفحه قبل
        </Link>
      ) : (
        <span className="text-text/40">صفحه قبل</span>
      )}
      <span className="text-text/60">
        صفحه {toFaDigits(page)} از {toFaDigits(totalPages)}
      </span>
      {next ? (
        <Link href={next} className="text-primary-600">
          صفحه بعد
        </Link>
      ) : (
        <span className="text-text/40">صفحه بعد</span>
      )}
    </nav>
  );
}
