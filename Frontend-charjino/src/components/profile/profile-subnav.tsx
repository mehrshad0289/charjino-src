'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { ROUTES } from '@/lib/routes';
import styles from './profile.module.css';

const ITEMS = [
  { href: ROUTES.profile, label: 'نمای کلی', match: 'exact' as const },
  { href: ROUTES.profileOrders, label: 'سفارش‌ها', match: 'prefix' as const },
  { href: ROUTES.profileTickets, label: 'تیکت‌ها', match: 'prefix' as const },
  { href: ROUTES.profileFavorites, label: 'علاقه‌مندی‌ها', match: 'prefix' as const },
  { href: ROUTES.profileWallet, label: 'کیف پول', match: 'exact' as const },
  { href: ROUTES.profileAccount, label: 'اطلاعات حساب', match: 'exact' as const },
];

function isActive(pathname: string, href: string, match: 'exact' | 'prefix') {
  if (match === 'exact') return pathname === href;
  return pathname === href || pathname.startsWith(`${href}/`);
}

export function ProfileSubnav() {
  const pathname = usePathname();

  return (
    <nav className={styles.subnav} aria-label="منوی پروفایل">
      {ITEMS.map((item) => {
        const current = isActive(pathname, item.href, item.match);
        return (
          <Link key={item.href} href={item.href} aria-current={current ? 'page' : undefined}>
            {item.label}
          </Link>
        );
      })}
    </nav>
  );
}
