'use client';

import { useRef, useState } from 'react';
import Link from 'next/link';
import { logout } from '@/actions/auth.actions';
import { Button } from '@/components/ui';
import { useOutsideDismiss } from '@/components/ui/field';
import { ProfileIcon } from '@/components/ui/icon';
import { ROUTES } from '@/lib/routes';

export function SiteAccountMenu() {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  useOutsideDismiss(open, ref, () => setOpen(false));

  return (
    <div className="relative" ref={ref}>
      <Button
        type="button"
        variant="outline"
        className="active:scale-100"
        icon={<ProfileIcon size={16} />}
        onClick={() => setOpen((value) => !value)}
      >
        حساب من
      </Button>
      {open ? (
        <div className="absolute top-full left-0 z-20 mt-2 w-48 rounded-xl border border-border bg-surface p-1 text-sm font-semibold text-text shadow-soft">
          <Link
            href={ROUTES.profile}
            className="block rounded-lg px-3 py-1.5 hover:bg-text/8"
            onClick={() => setOpen(false)}
          >
            حساب من
          </Link>
          <Link
            href={ROUTES.profileOrders}
            className="block rounded-lg px-3 py-1.5 hover:bg-text/8"
            onClick={() => setOpen(false)}
          >
            سفارش‌ها
          </Link>
          <form action={logout}>
            <button type="submit" className="block w-full rounded-lg px-3 py-1.5 text-right hover:bg-text/8">
              خروج
            </button>
          </form>
        </div>
      ) : null}
    </div>
  );
}
