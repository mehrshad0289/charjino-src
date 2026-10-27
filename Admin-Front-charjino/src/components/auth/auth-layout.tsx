import { AUTH_COPY } from '@/data/auth';
import { GridShape } from '@/components/ui/grid-shape';
import { ThemeTogglerTwo } from '@/components/ui/theme-toggler-two';
import Image from 'next/image';
import Link from 'next/link';
import type { ReactNode } from 'react';

type AuthLayoutProps = {
  children: ReactNode;
};

export function AuthLayout({ children }: AuthLayoutProps) {
  return (
    <div className='relative z-1 bg-white p-6 sm:p-0 dark:bg-gray-900'>
      <div className='relative flex min-h-screen w-full flex-col justify-center overflow-y-auto sm:p-0 lg:flex-row dark:bg-gray-900'>
        {children}
        <div className='hidden min-h-screen w-full items-center bg-brand-950 lg:grid lg:w-1/2 dark:bg-white/5'>
          <div className='relative z-1 flex items-center justify-center'>
            <GridShape />
            <div className='flex max-w-xs flex-col items-center'>
              <Link href='/signin' className='mb-4 block'>
                <Image
                  width={240}
                  height={101}
                  src='/images/logo/charjino-auth-logo.svg'
                  alt={AUTH_COPY.brandAlt}
                  priority
                />
              </Link>
              <p className='text-center text-gray-400 dark:text-white/60'>{AUTH_COPY.brandTagline}</p>
            </div>
          </div>
        </div>
        <div className='fixed end-6 bottom-6 z-50 hidden sm:block'>
          <ThemeTogglerTwo />
        </div>
      </div>
    </div>
  );
}
