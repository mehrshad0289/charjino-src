import { Spinner } from './spinner';

type PageLoaderProps = {
  label?: string;
};

export function PageLoader({ label = 'در حال بارگذاری...' }: PageLoaderProps) {
  return (
    <div
      className='flex min-h-[50vh] w-full flex-col items-center justify-center gap-4 py-16'
      role='status'
      aria-live='polite'
    >
      <Spinner size={56} />
      <p className='text-theme-sm font-medium text-gray-500 dark:text-gray-400'>{label}</p>
    </div>
  );
}
