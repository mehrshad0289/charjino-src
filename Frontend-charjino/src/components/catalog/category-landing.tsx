import Link from 'next/link';
import type { ReactNode } from 'react';
import { JsonLd } from '@/components/seo/json-ld';
import { brandHubBySlug } from '@/data/brand-hubs';
import { getCategoryLanding, shouldShowCategoryLanding } from '@/data/category-landing';
import { buildBrandHubLanding, googlePlayAmountRows } from '@/lib/brand-hub-landing';
import { resolvedInternalHref } from '@/lib/catalog-resolve';
import { toFaDigits } from '@/lib/fa';
import { faqPageJsonLd } from '@/lib/json-ld';
import type { CatalogProduct } from '@/types/catalog';

async function RichParagraph({ text }: { text: string }) {
  const token = /\[\[([^\]|]+)\|([^\]]+)\]\]/g;
  const matches = [...text.matchAll(token)];
  const hrefs = await Promise.all(matches.map((match) => resolvedInternalHref(match[1])));
  const nodes: ReactNode[] = [];
  let last = 0;
  let key = 0;
  for (const match of matches) {
    const index = match.index ?? 0;
    if (index > last) {
      nodes.push(text.slice(last, index));
    }
    nodes.push(
      <Link key={key} href={hrefs[key]} className="font-semibold text-primary-600 hover:underline">
        {match[2]}
      </Link>,
    );
    key += 1;
    last = index + match[0].length;
  }
  if (last < text.length) {
    nodes.push(text.slice(last));
  }
  return <p className="text-sm leading-8 text-text/80">{nodes}</p>;
}

export async function CategoryLanding({
  slug,
  q,
  page,
  platform,
  region,
  sort,
  products = [],
}: {
  slug: string;
  q: string;
  page: number;
  platform: string;
  region: string;
  sort: string;
  products?: CatalogProduct[];
}) {
  const hub = brandHubBySlug(slug);
  if (!shouldShowCategoryLanding({ slug, q, page, platform, region, sort, hasHub: Boolean(hub && hub.kind === 'category') })) {
    return null;
  }
  const landing = buildBrandHubLanding(slug, products) || getCategoryLanding(slug);
  if (!landing) {
    return null;
  }
  const amounts = slug === 'google-play' ? googlePlayAmountRows(products) : [];

  return (
    <div className="flex flex-col gap-8">
      <section aria-labelledby="category-intro-heading" className="flex flex-col gap-3">
        <h2 id="category-intro-heading" className="text-lg font-semibold text-text sm:text-xl">
          {landing.introTitle}
        </h2>
        {landing.paragraphs.map((paragraph, index) => (
          <RichParagraph key={index} text={paragraph} />
        ))}
        {amounts.length > 0 ? (
          <div className="overflow-x-auto text-sm leading-8 text-text/80">
            <table className="w-full border-collapse text-right">
              <thead>
                <tr>
                  <th className="border-b border-border py-2 font-semibold">مبلغ</th>
                  <th className="border-b border-border py-2 font-semibold">محصول</th>
                </tr>
              </thead>
              <tbody>
                {amounts.map((row) => (
                  <tr key={row.amount}>
                    <td className="border-b border-dashed border-border py-2">{toFaDigits(row.label)}</td>
                    <td className="border-b border-dashed border-border py-2">
                      {row.href ? (
                        <Link href={row.href} className="font-semibold text-primary-600 hover:underline">
                          {row.title || `گوگل پلی ${row.label}`}
                        </Link>
                      ) : (
                        'به‌زودی'
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}
      </section>
      <section aria-labelledby="category-faq-heading" className="flex flex-col gap-3">
        <h2 id="category-faq-heading" className="text-lg font-semibold text-text sm:text-xl">
          سؤالات پرتکرار
        </h2>
        <div className="flex flex-col gap-2">
          {landing.faqs.map((item, index) => (
            <details
              key={item.question}
              className="overflow-hidden rounded-2xl border border-border bg-card shadow-card open:border-primary-600/40"
              open={index === 0}
            >
              <summary className="cursor-pointer list-none px-4 py-3 text-sm font-semibold text-text marker:content-none [&::-webkit-details-marker]:hidden">
                {item.question}
              </summary>
              <p className="border-t border-dashed border-border px-4 py-3 text-sm leading-8 text-text/75">{item.answer}</p>
            </details>
          ))}
        </div>
      </section>
      <JsonLd data={faqPageJsonLd(landing.faqs.map((item) => ({ name: item.question, text: item.answer })))} />
    </div>
  );
}
