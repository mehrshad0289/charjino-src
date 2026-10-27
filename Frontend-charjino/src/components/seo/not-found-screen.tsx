import Link from 'next/link';
import { getCatalogCategories } from '@/actions/catalog.actions';
import { Button, Container } from '@/components/ui';
import { APP_NAME } from '@/lib/constants';
import { GAME_KEYS_SLUG, GIFT_CARD_SLUG } from '@/lib/catalog-family';
import { hotjarSuppress } from '@/lib/hotjar';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS } from '@/lib/seo';

const PINNED_SLUGS = ['game-topup', GIFT_CARD_SLUG, GAME_KEYS_SLUG];

type NotFoundScreenProps = {
  heading?: string;
  message?: string;
};

export async function NotFoundScreen({
  heading = 'صفحه پیدا نشد',
  message = 'این مسیر در شارژینو وجود ندارد. از فروشگاه یا دسته‌ها ادامه بده.',
}: NotFoundScreenProps) {
  const result = await getCatalogCategories();
  const items = (result.ok ? result.items : []).filter((item) => item.slug?.trim() && item.title?.trim());
  const pinned = PINNED_SLUGS.map((slug) => items.find((item) => item.slug === slug)).filter(
    (item): item is NonNullable<typeof item> => Boolean(item),
  );
  const rest = items.filter((item) => !PINNED_SLUGS.includes(item.slug));
  const categories = [...pinned, ...rest].slice(0, 8);

  return (
    <Container className="flex min-h-[50vh] flex-col items-center py-16 text-center">
      <title>{`${heading} | ${APP_NAME}`}</title>
      <meta name="description" content={PAGE_DESCRIPTIONS.notFound} />
      <meta name="robots" content="noindex, follow" />
      <h1 className="text-3xl font-bold text-text">{heading}</h1>
      <p className="mt-3 max-w-lg text-sm leading-7 text-text/60">{message}</p>
      <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
        <Button href={ROUTES.home} size="M">
          خانه
        </Button>
        <Button href={ROUTES.categories} variant="outline" size="M">
          فروشگاه
        </Button>
      </div>
      {categories.length > 0 ? (
        <nav className="mt-10 w-full max-w-2xl" aria-label="دسته‌های پرطرفدار">
          <p className="mb-4 text-sm font-semibold text-text">دسته‌های پرطرفدار</p>
          <ul className="flex flex-wrap justify-center gap-2">
            {categories.map((category) => (
              <li key={category.slug}>
                <Link
                  href={ROUTES.category(category.slug)}
                  className="inline-flex h-10 items-center rounded-xl border border-primary-600/30 bg-white px-3.5 text-sm font-bold text-primary-600 hover:bg-primary-600/5"
                >
                  {category.title}
                </Link>
              </li>
            ))}
          </ul>
        </nav>
      ) : null}
      <form action={ROUTES.categories} method="get" className="mt-10 flex w-full max-w-md flex-col gap-3 sm:flex-row">
        <label className="sr-only" htmlFor="not-found-search">
          جستجو در فروشگاه
        </label>
        <input
          {...hotjarSuppress}
          id="not-found-search"
          type="search"
          name="q"
          placeholder="جستجوی شارژ بازی و گیفت کارت"
          className="h-10 min-w-0 flex-1 rounded-md border-0 bg-gray-300 px-3 text-sm text-text outline-none placeholder:text-text/55 focus:ring-1 focus:ring-primary-600"
        />
        <Button type="submit" size="M">
          جستجو
        </Button>
      </form>
    </Container>
  );
}
