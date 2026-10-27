import { CatalogCover } from '@/components/catalog/catalog-cover';
import type { BuyGuideMedia } from '@/types/buy-guide';

export function GuideMedia({ media }: { media?: BuyGuideMedia | null }) {
  if (!media?.src) return null;
  return (
    <div className="my-3 overflow-hidden rounded-xl border border-border bg-card">
      <CatalogCover
        src={media.src}
        alt={media.alt ?? ''}
        width={media.width || 960}
        height={media.height || 540}
        sizes="(min-width: 1024px) 640px, 100vw"
        className="h-auto w-full"
        unoptimized={media.src.startsWith('/storage/')}
      />
    </div>
  );
}
