import Link from 'next/link';

export type FigmaIndexPageItem = {
  slug: string;
  href: string;
  title: string;
  frame: string;
  items: readonly string[];
};

type FigmaIndexProps = {
  fileUrl?: string;
  pages: readonly FigmaIndexPageItem[];
};

export function FigmaIndex({ fileUrl, pages }: FigmaIndexProps) {
  return (
    <main className='w-full min-w-0 box-border text-gray-700 dark:text-gray-300'>
      <p className='mb-2 text-sm text-gray-500 dark:text-gray-400'>پنل مدیریت شارژینو</p>
      <h1 className='mb-2 text-[clamp(1.35rem,4vw,1.75rem)] font-semibold text-gray-900 dark:text-white/90'>کامپوننت‌های گلوبال</h1>
      <p className='mb-4 max-w-[40rem] text-sm leading-[1.75] text-gray-500 dark:text-gray-400'>
        ظاهر از طرح ادمین ui ux است. معماری پوشه و برد از معماری ادمین کد.
      </p>
      {fileUrl ? (
        <a className='mb-6 inline-block text-sm text-gray-600' href={fileUrl} target='_blank' rel='noreferrer'>
          منبع طرح
        </a>
      ) : null}
      <ul className='m-0 grid list-none grid-cols-[repeat(auto-fit,minmax(min(100%,18rem),1fr))] gap-3 p-0'>
        {pages.map((page) => (
          <li key={page.slug}>
            <Link
              href={page.href}
              className='block h-full rounded-md border border-gray-200 bg-white p-4 no-underline hover:border-gray-400 dark:border-gray-800 dark:bg-white/3 dark:hover:border-gray-600'
            >
              <div className='flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2'>
                <strong className='text-gray-900 dark:text-white/90'>{page.title}</strong>
                <span className='text-xs text-gray-400'>{page.frame}</span>
              </div>
              <p className='mt-2 text-sm leading-[1.7] text-gray-500 dark:text-gray-400'>{page.items.join(' · ')}</p>
            </Link>
          </li>
        ))}
      </ul>
    </main>
  );
}
