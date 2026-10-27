import type { ReactNode } from 'react';
import Link from 'next/link';
import { toFaDigits } from '@/lib/fa';
import { ROUTES } from '@/lib/routes';
import styles from './profile.module.css';

type ProfilePagerProps = {
  page: number;
  totalPages: number;
  hrefFor: (page: number) => string;
};

export function ProfilePager({ page, totalPages, hrefFor }: ProfilePagerProps) {
  if (totalPages <= 1) return null;
  return (
    <div className="flex items-center gap-4 text-sm">
      {page > 1 ? (
        <Link href={hrefFor(page - 1)} className="text-primary-600">
          قبلی
        </Link>
      ) : null}
      <span className="text-text/60">
        صفحه {toFaDigits(page)} از {toFaDigits(totalPages)}
      </span>
      {page < totalPages ? (
        <Link href={hrefFor(page + 1)} className="text-primary-600">
          بعدی
        </Link>
      ) : null}
    </div>
  );
}

export function ProfileHeading({
  title,
  action,
}: {
  title: string;
  action?: ReactNode;
}) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-3">
      <h1 className="text-2xl font-bold text-text">{title}</h1>
      {action}
    </div>
  );
}

export function ProfileEmpty({ children }: { children: ReactNode }) {
  return <p className={`${styles.panel} text-center text-sm text-text/60`}>{children}</p>;
}

export function ProfileBack({ href = ROUTES.profile, label = 'بازگشت به حساب من' }: { href?: string; label?: string }) {
  return (
    <Link href={href} className="text-sm text-text/50 hover:text-primary-600">
      {label}
    </Link>
  );
}
