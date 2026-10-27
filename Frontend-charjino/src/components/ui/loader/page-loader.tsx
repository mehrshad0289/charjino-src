import { Spinner } from './spinner';

type PageLoaderProps = {
  label?: string;
};

export function PageLoader({ label = 'در حال بارگذاری...' }: PageLoaderProps) {
  return (
    <div className="flex min-h-[50vh] w-full flex-col items-center justify-center gap-4 py-16" role="status" aria-live="polite">
      <Spinner size={56} />
      <p className="text-sm font-medium text-text/70">{label}</p>
    </div>
  );
}
