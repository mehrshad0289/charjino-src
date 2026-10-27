'use client';

import { useSidebar } from '@/hooks/useSidebar';
import type { ReactNode } from 'react';

import { AppHeader } from '../app-header';
import { AppSidebar } from '../app-sidebar';
import { Backdrop } from '../backdrop';

export function AdminShell({ children }: { children: ReactNode }) {
  const { isExpanded, isHovered, isMobileOpen } = useSidebar();

  const mainContentMargin = isMobileOpen
    ? 'ms-0'
    : isExpanded || isHovered
      ? 'lg:ms-[290px]'
      : 'lg:ms-[90px]';

  return (
    <div className='min-h-screen xl:flex'>
      <AppSidebar />
      <Backdrop />
      <div className={`flex-1 transition-all duration-300 ease-in-out ${mainContentMargin}`}>
        <AppHeader />
        <div className='mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6'>{children}</div>
      </div>
    </div>
  );
}
