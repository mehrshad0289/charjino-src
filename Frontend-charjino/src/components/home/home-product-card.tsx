"use client";

import Link from "next/link";
import type { ReactNode } from "react";
import { CatalogCover } from "@/components/catalog/catalog-cover";
import { ProductCardFavorite } from "@/components/profile/favorite-toggle";
import { LocationIcon, MonitorIcon } from "@/components/ui/icon";
import { gameKeyCardPlatformLabel } from "@/lib/catalog-family";
import { catalogRegionLabel } from "@/lib/catalog-region";
import { productImageAlt } from "@/lib/image-alt";
import { formatFromToman } from "@/lib/price";
import { ROUTES } from "@/lib/routes";
import type { CatalogProduct } from "@/types/catalog";

type HomeProductCardProps = {
  product: CatalogProduct;
  href?: string;
  priority?: boolean;
};

function homeCardBlurb(product: CatalogProduct, region: string) {
  const raw = (product.shortDescription || product.subtitle || "").replace(/\s+/g, " ").trim();
  if (raw && !/لورم|ایپسوم|اپیسوم|lorem\s*ipsum/i.test(raw)) {
    return raw;
  }
  const name = [product.title?.trim(), region].filter(Boolean).join(" ") || "محصول";
  return `خرید ${name} با تومان و تحویل فوری از شارژینو`;
}

function CoverChip({ icon, label }: { icon: ReactNode; label: string }) {
  return (
    <span
      dir="ltr"
      className="inline-flex max-w-full items-center gap-1 rounded-md bg-background/80 px-1.5 py-0.5 text-[10px] font-medium text-text ring-1 ring-border/70"
    >
      <span className="shrink-0 text-text/80">{icon}</span>
      <span className="truncate">{label}</span>
    </span>
  );
}

export function HomeProductCard({
  product,
  href,
  priority = false,
}: HomeProductCardProps) {
  const fromPrice = formatFromToman(product.minPrice);
  const platform =
    gameKeyCardPlatformLabel(product.platform) ||
    product.platform?.trim() ||
    "";
  const region = catalogRegionLabel(product.region);
  const blurb = homeCardBlurb(product, region);

  return (
    <div className="relative h-full">
      <Link
        href={href ?? ROUTES.catalog(product.handle)}
        className="block h-full"
      >
        <div className="flex h-full rounded-md overflow-hidden bg-linear-to-l from-[#12327B] transition-all duration-300 ease-in-out ">
          <div className="relative aspect-square w-[40%] shrink-0 bg-card-muted rounded-l-md overflow-hidden">
            <CatalogCover
              src={product.thumbnail}
              alt={productImageAlt(product.title, region)}
              fill
              fit="contain"
              priority={priority}
              sizes="(min-width: 1280px) 16vw, (min-width: 640px) 25vw, 50vw"
            />
            {platform || region ? (
              <div className="absolute inset-x-0 bottom-0 flex flex-wrap items-center gap-1 bg-gradient-to-t from-background/85 to-transparent p-2">
                {platform ? (
                  <CoverChip
                    icon={<MonitorIcon size={12} />}
                    label={platform}
                  />
                ) : null}
                {region ? (
                  <CoverChip icon={<LocationIcon size={12} />} label={region} />
                ) : null}
              </div>
            ) : null}
          </div>
          <article className="flex flex-col gap-0.5 py-3 pr-3 text-text-muted">
            <h2 className="truncate text-md font-bold">
              {product.title}
            </h2>
            <p className="line-clamp-3 text-wrap! text-xs word-break-all">
              {blurb}
            </p>
            <p className="truncate text-text-muted md:text-xs">
              {product.publisher || " "}
            </p>
            <p className=" truncate text-white md:text-xs">
              {fromPrice || " "}
            </p>
          </article>
        </div>
      </Link>
      <ProductCardFavorite productId={product.id} />
    </div>
  );
}
