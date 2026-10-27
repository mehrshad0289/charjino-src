import { FazerCardsSubnav } from '@/components/admin/fazer-cards/fazer-cards-subnav';
import type { ReactNode } from 'react';

export default function FazerCardsLayout({ children }: { children: ReactNode }) {
  return (
    <div>
      <FazerCardsSubnav />
      {children}
    </div>
  );
}
