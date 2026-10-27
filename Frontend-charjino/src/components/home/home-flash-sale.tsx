import Link from "next/link";
import { CatalogCover } from "@/components/catalog/catalog-cover";
import { Container } from "@/components/ui/container";
import { CountdownTimer } from "@/components/ui/countdown";
import { productImageAlt } from "@/lib/image-alt";
import { formatTomanNumber } from "@/lib/price";
import type { FlashSaleItem } from "@/types/catalog";
import { HomeMarquee } from "./home-marquee";

type HomeFlashSaleProps = {
  items: FlashSaleItem[];
  title?: string;
  subtitle?: string;
  endsAt?: string | null;
};

type MarqueeEntry = {
  key: string;
  item: FlashSaleItem;
};

/** Enough width for a smooth infinite marquee when the API returns few items. */
function marqueeEntries(items: FlashSaleItem[]): MarqueeEntry[] {
  if (items.length === 0) return [];
  const copies = items.length >= 4 ? 1 : Math.max(2, Math.ceil(8 / items.length));
  return Array.from({ length: copies }, (_, copy) =>
    items.map((item) => ({ key: `${item.id}-${copy}`, item })),
  ).flat();
}

function discountPercent(original: number, price: number) {
  if (original <= 0 || price >= original) return 0;
  return Math.round(((original - price) / original) * 100);
}

function FlashCard({ item }: { item: FlashSaleItem }) {
  const saved = Math.max(0, item.originalPrice - item.price);
  const off = discountPercent(item.originalPrice, item.price);

  return (
    <Link
      href={`/catalog/${item.productHandle}`}
      aria-label={`${item.productName}، ${item.productVariantName || item.productVariantCode}، ${formatTomanNumber(item.price)} تومان`}
      className="group relative flex w-[200px] shrink-0 flex-col overflow-hidden rounded-xl border border-border bg-card p-3 font-semibold shadow-card transition-[transform,box-shadow,border-color] duration-200 hover:-translate-y-0.5 hover:border-primary-600/35 hover:shadow-lg sm:w-[265px] dark:border-[#1C3571] dark:bg-linear-to-br dark:from-[#0E3077]/95 dark:to-[#07183F] dark:shadow-[0_8px_28px_-12px_rgb(0_0_0/0.45)] dark:hover:border-[#00e5ff]/45 dark:hover:shadow-[0_12px_32px_-10px_rgb(0_229_255/0.28)]"
    >
      <div className="pointer-events-none absolute -inset-e-8 -top-10 size-28 rounded-full bg-primary-600/10 blur-2xl opacity-0 transition-opacity duration-300 group-hover:opacity-100 dark:bg-[#00e5ff]/15" />

      {off > 0 ? (
        <span className="absolute top-2.5 inset-s-2.5 z-10 rounded-md bg-sale px-1.5 py-0.5 text-[10px] font-bold leading-none text-white shadow-sm">
          {off}٪
        </span>
      ) : null}

      <div className="flex min-w-0 flex-col items-center gap-3">
        <div className="relative flex aspect-video w-full shrink-0 items-center justify-center overflow-hidden rounded-lg bg-card-muted ring-1 ring-border dark:bg-[#07183F]/80 dark:ring-[#1C3571]">
          <CatalogCover
            src={item.image ?? ""}
            alt={productImageAlt(item.productName)}
            height={48}
            width={48}
            fit="contain"
            className="h-full w-full object-contain p-1 transition-transform duration-300 group-hover:scale-105"
          />
        </div>

        <div className="flex min-w-0 flex-col items-center justify-center gap-1 text-center">
          <p className="line-clamp-2 w-full text-sm leading-5 text-text">
            {item.productName}
          </p>
          <div className="flex min-w-0 items-center justify-center gap-1 text-xs">
            <p className="truncate text-text-muted line-through">
              {formatTomanNumber(item.originalPrice)} تومان
            </p>
            <span className="text-text-muted/70" aria-hidden>
              |
            </span>
            <p className="truncate text-text">{formatTomanNumber(item.price)} تومان</p>
          </div>
        </div>

        <p className="line-clamp-2 w-full text-center text-lg font-bold leading-5 text-primary-600 dark:text-primary-300">
          {item.productVariantName}
        </p>
      </div>

      {saved > 0 ? (
        <div className="pointer-events-none absolute top-0 size-24 overflow-hidden inset-e-0 z-20">
          <span className="absolute top-4 w-36 -rotate-45 bg-linear-to-r from-red-500 to-red-600 py-1 text-center text-[9px] leading-none font-bold text-white shadow inset-e-[-1.85rem]">
            صرفه‌جویی {formatTomanNumber(saved)}
          </span>
        </div>
      ) : null}
    </Link>
  );
}

export function HomeFlashSale({
  items,
  title,
  subtitle,
  endsAt,
}: HomeFlashSaleProps) {
  if (items.length === 0) return null;

  const entries = marqueeEntries(items);

  return (
    <Container className="flex min-w-0 max-w-full flex-col">
      <section
        className="relative w-full min-w-0 max-w-full overflow-hidden rounded-2xl border border-border bg-linear-to-br from-primary-50/70 via-card to-canvas p-4 sm:p-5 dark:border-[#1C3571] dark:from-[#0E3077]/35 dark:via-[#07183F]/40 dark:to-canvas dark:shadow-[inset_0_1px_0_rgb(255_255_255/0.05)]"
        aria-labelledby="home-flash-sale-heading"
      >
        <div
          className="pointer-events-none absolute -top-16 inset-e-0 size-40 rounded-full bg-primary-600/15 blur-3xl dark:bg-[#00e5ff]/10"
          aria-hidden
        />

        <div className="relative flex flex-col gap-3 pb-1">
          <div className="flex items-start justify-between gap-x-4 gap-y-3">
            <div className="min-w-0">
              <h2
                id="home-flash-sale-heading"
                className="text-lg font-bold leading-relaxed tracking-wide text-text sm:text-xl"
              >
                <span aria-hidden className="me-1">
                  ⚡
                </span>
                {title ?? "فروش ویژه"}
              </h2>
              <p className="mt-1 text-xs text-text-muted">
                {subtitle ?? "همین حالا سفارش بده؛ موجودی محدود است."}
              </p>
            </div>
            <CountdownTimer endsAt={endsAt} />
          </div>
        </div>

        <div className="-mx-1 min-w-0 w-full max-w-full overflow-hidden py-2">
          <HomeMarquee gap={16}>
            {entries.map(({ key, item }) => (
              <FlashCard key={key} item={item} />
            ))}
          </HomeMarquee>
        </div>
      </section>
    </Container>
  );
}
