import Link from 'next/link';
import { BuyGuideTabs } from '@/components/catalog/buy-guide/buy-guide-tabs';
import { GuideMedia } from '@/components/catalog/buy-guide/guide-media';
import { VideoFacade } from '@/components/catalog/buy-guide/video-facade';
import { formatJalaliIso } from '@/lib/jalali';
import { hasStructuredBuyGuide, presentBuyGuide, sectionOf } from '@/lib/buy-guide';
import { ROUTES } from '@/lib/routes';
import type {
  BuyGuide,
  BuyGuideError,
  BuyGuideFaq,
  BuyGuideField,
  BuyGuideSource,
  BuyGuideStep,
  BuyGuideVideo,
} from '@/types/buy-guide';
import type { CatalogProduct } from '@/types/catalog';

function Steps({ steps }: { steps: BuyGuideStep[] }) {
  return (
    <ol className="space-y-4">
      {steps.map((step, index) => (
        <li key={`${step.text}-${index}`} className="rounded-xl border border-border bg-card p-4 shadow-card">
          <p className="text-sm leading-7 text-text">
            <span className="me-2 inline-flex size-6 items-center justify-center rounded-full bg-primary-600 text-xs text-white">
              {index + 1}
            </span>
            {step.text}
          </p>
          <GuideMedia media={step.media} />
        </li>
      ))}
    </ol>
  );
}

export function BuyGuideSection({
  guide,
  product,
}: {
  guide?: BuyGuide | null;
  product: CatalogProduct;
}) {
  const presented = presentBuyGuide(guide);
  if (!hasStructuredBuyGuide(presented) || !presented) {
    return <div id="buy-guide-slot" className="hidden" />;
  }
  const before = sectionOf(presented, 'before_buy');
  const fields = sectionOf(presented, 'fields');
  const purchase = sectionOf(presented, 'purchase_steps');
  const redeem = sectionOf(presented, 'redeem');
  const regions = sectionOf(presented, 'regions');
  const errors = sectionOf(presented, 'errors');
  const faq = sectionOf(presented, 'faq');
  const videos = sectionOf(presented, 'videos');
  const sources = sectionOf(presented, 'sources');
  const reviewed = formatJalaliIso(presented.lastReviewedAt);
  const categoryHref = presented.scope?.category ? ROUTES.category(presented.scope.category) : ROUTES.categories;

  return (
    <div id="buy-guide-slot">
      <section
        id="buy-guide"
        aria-labelledby="buy-guide-title"
        className="mx-auto mt-8 max-w-3xl min-w-0 overflow-x-hidden rounded-2xl border border-border bg-card p-4 shadow-card sm:p-6"
      >
        <div className="mb-4 flex flex-wrap items-end justify-between gap-2">
          <h2 id="buy-guide-title" className="text-xl font-semibold text-text">
            {presented.title || `راهنمای خرید ${product.title}`}
          </h2>
          {reviewed ? <p className="text-xs text-text/55">آخرین بازبینی: {reviewed}</p> : null}
        </div>
        {presented.summary ? <p className="mb-4 text-sm leading-7 text-text/80">{presented.summary}</p> : null}
        {presented.introHtml ? (
          <div
            className="prose-guide mb-6 max-w-full min-w-0 overflow-x-hidden text-sm leading-7 break-words text-text [&_a]:break-all"
            dangerouslySetInnerHTML={{ __html: presented.introHtml }}
          />
        ) : null}

        {before?.notes?.length ? (
          <section className="mb-6">
            <h2 className="mb-3 text-lg font-semibold text-text">قبل از خرید بدانید</h2>
            <ul className="space-y-2">
              {before.notes.map((note) => (
                <li
                  key={note.text}
                  className={`rounded-xl px-3 py-2 text-sm leading-7 ${
                    note.tone === 'warning' ? 'bg-primary-600/10 text-text' : 'bg-card-muted text-text/80'
                  }`}
                >
                  {note.text}
                </li>
              ))}
            </ul>
          </section>
        ) : null}

        {fields?.items?.length ? (
          <section className="mb-6">
            <h2 className="mb-3 text-lg font-semibold text-text">اطلاعات لازم برای خرید</h2>
            <dl className="space-y-3">
              {(fields.items as BuyGuideField[]).map((item) => (
                <div key={item.label} className="rounded-xl border border-border p-3">
                  <dt className="text-sm font-medium text-text">{item.label}</dt>
                  {item.help ? <dd className="mt-1 text-sm leading-7 text-text/70">{item.help}</dd> : null}
                  <GuideMedia media={item.media} />
                </div>
              ))}
            </dl>
          </section>
        ) : null}

        {purchase?.steps?.length ? (
          <section className="mb-6">
            <h2 className="mb-3 text-lg font-semibold text-text">مراحل خرید در شارژینو</h2>
            <Steps steps={purchase.steps} />
          </section>
        ) : null}

        {redeem?.tabs?.length ? (
          <section className="mb-6">
            <h2 className="mb-3 text-lg font-semibold text-text">فعال‌سازی</h2>
            <BuyGuideTabs
              tabs={redeem.tabs.map((tab) => ({
                key: tab.key,
                label: tab.label,
                panel: (
                  <div>
                    <Steps steps={tab.steps ?? []} />
                    <GuideMedia media={tab.media} />
                  </div>
                ),
              }))}
            />
          </section>
        ) : null}

        {regions?.rows?.length ? (
          <section className="mb-6">
            <h2 className="mb-3 text-lg font-semibold text-text">ریجن‌ها</h2>
            <div className="overflow-x-auto rounded-xl border border-border">
              <table className="min-w-full text-sm">
                <thead className="sticky top-0 bg-card-muted">
                  <tr>
                    <th className="px-3 py-2 text-start font-medium">ریجن</th>
                    <th className="px-3 py-2 text-start font-medium">سازگاری</th>
                    <th className="px-3 py-2 text-start font-medium">محصول</th>
                  </tr>
                </thead>
                <tbody>
                  {regions.rows.map((row) => (
                    <tr
                      key={`${row.code}-${row.product_handle}`}
                      className={row.current ? 'bg-primary-600/10' : 'odd:bg-card-muted/40'}
                    >
                      <td className="px-3 py-2">{row.label}</td>
                      <td className="px-3 py-2 text-text/70">{row.compat}</td>
                      <td className="px-3 py-2">
                        {row.product_handle ? (
                          <Link className="text-primary-600" href={ROUTES.catalog(row.product_handle)}>
                            مشاهده
                          </Link>
                        ) : null}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <p className="mt-2 text-xs text-text/55">
              <Link href={categoryHref} className="text-primary-600">
                بازگشت به دسته
              </Link>
            </p>
          </section>
        ) : null}

        {videos?.items?.length ? (
          <section className="mb-6">
            <h2 className="mb-3 text-lg font-semibold text-text">ویدیو آموزشی</h2>
            <div className="grid gap-4">
              {(videos.items as BuyGuideVideo[]).map((video) => (
                <VideoFacade key={`${video.provider}-${video.url}`} video={video} />
              ))}
            </div>
          </section>
        ) : null}

        {errors?.items?.length ? (
          <section className="mb-6">
            <h2 className="mb-3 text-lg font-semibold text-text">خطاهای رایج</h2>
            {(errors.items as BuyGuideError[]).map((item) => (
              <div key={item.error} className="mb-3">
                <h3 className="text-base font-medium text-text">{item.error}</h3>
                <p className="text-sm leading-7 text-text/70">{item.fix}</p>
              </div>
            ))}
          </section>
        ) : null}

        {faq?.items?.length ? (
          <section className="mb-6">
            <h2 className="mb-3 text-lg font-semibold text-text">سؤالات متداول</h2>
            {(faq.items as BuyGuideFaq[]).map((item) => (
              <details key={item.q} className="mb-2 rounded-xl border border-border px-3 py-2">
                <summary className="cursor-pointer text-sm font-medium text-text">
                  <h3 className="inline text-sm font-medium">{item.q}</h3>
                </summary>
                <p className="mt-2 text-sm leading-7 text-text/70">{item.a}</p>
              </details>
            ))}
          </section>
        ) : null}

        {sources?.items?.length ? (
          <section className="min-w-0">
            <h2 className="mb-3 text-lg font-semibold text-text">منابع</h2>
            <ul className="flex min-w-0 flex-col gap-2">
              {(sources.items as BuyGuideSource[]).map((item) => (
                <li key={item.url} className="min-w-0">
                  <a
                    href={item.url}
                    className="block max-w-full break-all text-sm leading-6 text-primary-600"
                    target="_blank"
                    rel="nofollow noopener noreferrer"
                  >
                    {item.label || item.url}
                  </a>
                </li>
              ))}
            </ul>
          </section>
        ) : null}
      </section>
    </div>
  );
}
