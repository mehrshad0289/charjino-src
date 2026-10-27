import { ERROR_404_COPY } from '@/data/error';
import { GridShape } from '@/components/ui/grid-shape';
import Image from 'next/image';
import Link from 'next/link';

export function Error404Screen() {
  return (
    <div className='relative z-1 flex min-h-screen flex-col items-center justify-center overflow-hidden p-6'>
      <GridShape />
      <div className='mx-auto w-full max-w-[242px] text-center sm:max-w-[472px]'>
        <h1 className='text-title-md mb-8 font-bold text-gray-800 xl:text-title-2xl dark:text-white/90'>
          {ERROR_404_COPY.title}
        </h1>
        <Image src='/images/error/404.svg' alt='404' className='dark:hidden' width={472} height={152} />
        <Image src='/images/error/404-dark.svg' alt='404' className='hidden dark:block' width={472} height={152} />
        <p className='mt-10 mb-6 text-base text-gray-700 sm:text-lg dark:text-gray-400'>{ERROR_404_COPY.message}</p>
        <Link
          href='/'
          className='inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-3.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/3 dark:hover:text-gray-200'
        >
          {ERROR_404_COPY.backHome}
        </Link>
      </div>
      <p className='absolute bottom-6 left-1/2 -translate-x-1/2 text-center text-sm text-gray-500 dark:text-gray-400'>
        &copy; {new Date().getFullYear()} - {ERROR_404_COPY.footer}
      </p>
    </div>
  );
}
