import { faqPageJsonLd } from '@/lib/json-ld';
import { productLandingCopy } from '@/lib/product-landing';
import { JsonLd } from '@/components/seo/json-ld';
import type { CatalogProduct } from '@/types/catalog';

export function ProductLanding({ product, withSchema = false }: { product: CatalogProduct; withSchema?: boolean }) {
  const copy = productLandingCopy(product);

  return (
    <section className="flex min-w-0 flex-col gap-6" aria-labelledby="product-landing-heading">
      <h2 id="product-landing-heading" className="text-lg font-semibold text-text">
        راهنمای خرید
      </h2>
      <div className="flex flex-col gap-4 text-sm leading-8 text-text/80">
        <section>
          <h3 className="mb-1 text-base font-semibold text-text">معرفی</h3>
          <p>{copy.intro}</p>
        </section>
        <section>
          <h3 className="mb-1 text-base font-semibold text-text">روش استفاده</h3>
          <p>{copy.howTo}</p>
        </section>
        <section>
          <h3 className="mb-1 text-base font-semibold text-text">ریجن</h3>
          <p>{copy.region}</p>
        </section>
        <section>
          <h3 className="mb-1 text-base font-semibold text-text">زمان تحویل</h3>
          <p>{copy.delivery}</p>
        </section>
        <section>
          <h3 className="mb-2 text-base font-semibold text-text">سؤال‌های متداول</h3>
          <div className="flex flex-col gap-3">
            {copy.faqs.map((item) => (
              <details key={item.q} className="rounded-2xl bg-card px-4 py-3 ring-1 ring-border">
                <summary className="cursor-pointer text-sm font-semibold text-text">{item.q}</summary>
                <p className="mt-2 text-sm leading-7 text-text/70">{item.a}</p>
              </details>
            ))}
          </div>
        </section>
      </div>
      {withSchema ? (
        <JsonLd data={faqPageJsonLd(copy.faqs.map((item) => ({ name: item.q, text: item.a })))} />
      ) : null}
    </section>
  );
}
