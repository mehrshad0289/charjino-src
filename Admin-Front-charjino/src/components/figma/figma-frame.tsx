import type { ReactNode } from 'react';
import Link from 'next/link';

type FigmaFrameProps = {
  title: string;
  frame?: string;
  className?: string;
  children: ReactNode;
};

export function FigmaFrame({ title, frame, className, children }: FigmaFrameProps) {
  return (
    <main
      className={`w-full min-w-0 box-border text-gray-700 ${className ?? ''}`}
    >
      <p
        className='mb-[clamp(16px,3vw,32px)] flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500'
        data-figma-crumb
      >
        <Link href='/figma' className='text-inherit no-underline hover:text-gray-800'>
          گلوبال
        </Link>
        <span>·</span>
        <span>{title}</span>
        {frame ? <span className='text-gray-400 max-[480px]:hidden'>· {frame}</span> : null}
      </p>
      <div className='w-full min-w-0'>{children}</div>
    </main>
  );
}
