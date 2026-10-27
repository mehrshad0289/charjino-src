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

type CatalogProductCardProps = {
  product: CatalogProduct;
  href?: string;
  priority?: boolean;
};

function CoverChip({ icon, label }: { icon: ReactNode; label: string }) {
  return (
    <div
      dir="ltr"
      className="inline-flex max-w-full items-center gap-1 rounded-sm bg-background/80 px-1.5 text-[8px] font-medium text-text ring-1 ring-border/70"
    >
      <span className="shrink-0 text-text/80">{icon}</span>
      <p className="truncate">{label}</p>
    </div>
  );
}

export function CatalogProductCard({
  product,
  href,
  priority = false,
}: CatalogProductCardProps) {
  const fromPrice = formatFromToman(product.minPrice);
  const platform =
    gameKeyCardPlatformLabel(product.platform) ||
    product.platform?.trim() ||
    "";
  const region = catalogRegionLabel(product.region);
  const publisher = product.publisher?.trim() || "";

  return (
    <div className="relative h-full mt-[50%]">
      <Link
        href={href ?? ROUTES.catalog(product.handle)}
        className="block h-full"
      >
        <div className="flex justify-end h-[12rem] flex-col rounded-md bg-card shadow-card ring-1 ring-border transition-all duration-300 ease-in-out hover:ring-2 hover:ring-primary-600 hover:ring-offset-2 hover:ring-offset-background">
          <div className="absolute transform translate-y-[-50%]  top-0 left-0 right-0 bottom-0 px-4 aspect-4/4 w-full shrink-0 ">
            <div className="relative w-full h-full rounded-sm overflow-hidden  shrink-0">
              <CatalogCover
                className="w-full h-full object-cover bg-accent"
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
                    <CoverChip
                      icon={<LocationIcon size={12} />}
                      label={region}
                    />
                  ) : null}
                </div>
              ) : null}
              <ProductCardFavorite productId={product.id} />
            </div>
          </div>
          <article className="flex h-[56%] shrink-0 flex-col justify-between items-center text-center gap-0.5  px-4 pt-1 pb-3 sm:px-4 font-semibold text-sm text-wrap">
            <div>
              <h2 className="line-clamp-2 font-bold">{product.title}</h2>
              {publisher ? (
                <p className="truncate text-text-muted  ">{publisher}</p>
              ) : null}
            </div>
            <div className="flex flex-col items-center justify-center gap-1 w-full">
              <p className="truncate text-primary-600 ">{fromPrice || " "}</p>
              <button className="w-full bg-linear-to-b from-[#619AFF] to-[#AAD9FF] text-white px-4 py-1.5 rounded-xs text-xs font-normal">
                دیدن حزییات
              </button>
            </div>
          </article>
        </div>
      </Link>
    </div>
  );
}
