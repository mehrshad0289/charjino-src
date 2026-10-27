'use client';

import { useEffect, useState } from 'react';
import { Button, FormattedText, Modal } from '@/components/ui';
import { catalogImageSrc } from '@/lib/catalog-image';
import { toFaDigits } from '@/lib/fa';
import {
  isPurchaseGuideEmbed,
  normalizePurchaseGuideItems,
  purchaseGuideEmbedSrc,
  type PurchaseGuideItem,
} from '@/lib/purchase-guide';

type PurchaseGuideModalProps = {
  open: boolean;
  onClose: () => void;
  title?: string;
  body?: string | null;
  items?: PurchaseGuideItem[] | null;
};

export function PurchaseGuideModal({
  open,
  onClose,
  title = 'راهنمای خرید',
  body,
  items,
}: PurchaseGuideModalProps) {
  const slides = normalizePurchaseGuideItems(items, body);
  const [index, setIndex] = useState(0);

  useEffect(() => {
    if (open) setIndex(0);
  }, [open]);

  const current = slides[index];
  const total = slides.length;
  const canPrev = index > 0;
  const canNext = index < total - 1;

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={title}
      bodyClassName="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4"
      footer={
        total > 1 ? (
          <div className="flex items-center justify-between gap-3">
            <Button type="button" variant="outline" size="M" leftIcon={false} rightIcon={false} disabled={!canPrev} onClick={() => setIndex((value) => Math.max(0, value - 1))}>
              قبلی
            </Button>
            <span className="text-xs text-text/55">
              {toFaDigits(index + 1)} از {toFaDigits(total)}
            </span>
            <Button type="button" variant="outline" size="M" leftIcon={false} rightIcon={false} disabled={!canNext} onClick={() => setIndex((value) => Math.min(total - 1, value + 1))}>
              بعدی
            </Button>
          </div>
        ) : null
      }
    >
      {current ? (
        <GuideSlide item={current} />
      ) : (
        <p className="text-sm leading-7 text-text/60">هنوز برای این دسته راهنمای خریدی ثبت نشده است.</p>
      )}
      {total > 1 ? (
        <div className="mt-4 flex justify-center gap-1.5">
          {slides.map((slide, slideIndex) => (
            <button
              key={`${slide.type}-${slideIndex}`}
              type="button"
              aria-label={`اسلاید ${slideIndex + 1}`}
              className={`h-2 rounded-full transition-all ${
                slideIndex === index ? 'w-5 bg-primary-600' : 'w-2 bg-border'
              }`}
              onClick={() => setIndex(slideIndex)}
            />
          ))}
        </div>
      ) : null}
    </Modal>
  );
}

function GuideSlide({ item }: { item: PurchaseGuideItem }) {
  const src = item.src?.trim() ?? '';

  return (
    <div className="flex flex-col gap-3">
      {item.title ? <h3 className="text-base font-semibold text-text">{item.title}</h3> : null}
      {item.type === 'text' ? (
        <FormattedText text={item.body} className="text-sm leading-7 text-text/80" />
      ) : null}
      {item.type === 'video' && src ? (
        isPurchaseGuideEmbed(src) ? (
          <div className="aspect-video overflow-hidden rounded-2xl bg-card-muted">
            <iframe
              src={purchaseGuideEmbedSrc(src)}
              title={item.title || 'ویدیوی راهنمای خرید'}
              className="h-full w-full border-0"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowFullScreen
            />
          </div>
        ) : (
          <video src={catalogImageSrc(src)} className="w-full rounded-2xl bg-black" controls playsInline />
        )
      ) : null}
      {(item.type === 'image' || item.type === 'gif') && src ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={catalogImageSrc(src)}
          alt={item.caption || item.title || 'تصویر راهنمای خرید'}
          width={800}
          height={450}
          className="h-auto w-full rounded-2xl object-contain"
        />
      ) : null}
      {item.caption ? <p className="text-xs leading-6 text-text/55">{item.caption}</p> : null}
    </div>
  );
}
