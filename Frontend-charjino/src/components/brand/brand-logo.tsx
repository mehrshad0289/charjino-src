import Image from 'next/image';

/** ابعاد از `brand-assets/README.md` — لوگوی فارسی زمینهٔ تیره 111×40 */
export const BRAND_WORDMARK_FA_DARK = {
  src: '/image/brand/logo-fa-dark.svg',
  width: 111,
  height: 40,
} as const;

export const BRAND_WORDMARK_FA_LIGHT = {
  src: '/image/brand/logo-fa-light.svg',
  width: 119,
  height: 40,
} as const;

/** نشان بدون نوشته؛ معادل `logo-mark-h72.png` یعنی 56×72 */
export const BRAND_MARK = {
  src: '/image/brand/logo-mark.svg',
  width: 56,
  height: 72,
} as const;

type BrandWordmarkProps = {
  priority?: boolean;
};

function BrandMarkTile({
  size,
  priority = false,
}: {
  size: number;
  priority?: boolean;
}) {
  const innerW = Math.round(size * 0.55);
  const innerH = Math.round(size * 0.72);
  return (
    <span
      className="inline-flex shrink-0 items-center justify-center rounded-lg dark:ring-0"
      style={{ width: size, height: size }}
    >
      <Image
        src={BRAND_MARK.src}
        alt=""
        width={innerW}
        height={innerH}
        priority={priority}
        className="object-contain"
      />
    </span>
  );
}

export function BrandWordmark({ priority = false }: BrandWordmarkProps) {
  return (
    <span className="inline-flex items-center">
      <BrandMarkTile size={40} priority={priority} />
        <span className="text-xl font-bold">شارژینو</span>
    </span>
  );
}

export function BrandMark({ priority = false }: BrandWordmarkProps) {
  return <BrandMarkTile size={72} priority={priority} />;
}
