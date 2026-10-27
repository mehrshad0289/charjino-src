import Image from 'next/image';
import { catalogImageSrc } from '@/lib/catalog-image';

type CatalogCoverProps = {
  src: string | null | undefined;
  alt: string;
  className?: string;
  fill?: boolean;
  fit?: 'contain' | 'cover';
  width?: number;
  height?: number;
  priority?: boolean;
  sizes?: string;
  unoptimized?: boolean;
  decorative?: boolean;
};

export function CatalogCover({
  src,
  alt,
  className,
  fill = false,
  fit = 'contain',
  width,
  height,
  priority = false,
  sizes,
  unoptimized = false,
  decorative = false,
}: CatalogCoverProps) {
  const resolved = catalogImageSrc(src);
  const fitClass = fit === 'cover' ? 'object-cover' : 'object-contain object-center';
  const classes = `${fitClass} ${className ?? ''}`.trim();
  const hidden = decorative || !alt.trim();
  const imageAlt = hidden ? '' : alt;

  if (fill) {
    return (
      <Image
        src={resolved}
        alt={imageAlt}
        fill
        priority={priority}
        fetchPriority={priority ? 'high' : 'auto'}
        sizes={sizes ?? '100vw'}
        unoptimized={unoptimized}
        aria-hidden={hidden ? true : undefined}
        className={classes}
      />
    );
  }

  return (
    <Image
      src={resolved}
      alt={imageAlt}
      width={width ?? 288}
      height={height ?? 192}
      priority={priority}
      fetchPriority={priority ? 'high' : 'auto'}
      sizes={sizes}
      unoptimized={unoptimized}
      aria-hidden={hidden ? true : undefined}
      className={classes}
    />
  );
}
