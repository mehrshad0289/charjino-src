'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

import { FAZER_CARDS_NAV } from '@/lib/fazer-cards-nav';

export function FazerCardsSubnav() {
  const pathname = usePathname();

  return (
    <nav className='mb-4 flex flex-wrap gap-2' aria-label='FazerCards'>
      {FAZER_CARDS_NAV.map((item) => {
        const active = pathname === item.href;
        return (
          <Link
            key={item.key}
            href={item.href}
            className={
              active
                ? 'rounded-lg bg-brand-500 px-3 py-1.5 text-theme-xs font-medium text-white'
                : 'rounded-lg bg-gray-100 px-3 py-1.5 text-theme-xs font-medium text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-400 dark:hover:bg-white/10'
            }
          >
            {item.label}
          </Link>
        );
      })}
    </nav>
  );
}
