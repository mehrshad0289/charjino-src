import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { Button, Chip, Container } from '@/components/ui';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export function generateMetadata(): Metadata {
  if (process.env.NODE_ENV === 'production') {
    notFound();
  }
  return buildMetadata({
    title: 'کاتالوگ توکن‌ها',
    description: PAGE_DESCRIPTIONS.figma,
    path: ROUTES.figma,
    noindex: true,
  });
}

const SCALES = [
  {
    name: 'primary',
    vars: [
      '--color-primary-50',
      '--color-primary-100',
      '--color-primary-200',
      '--color-primary-300',
      '--color-primary-400',
      '--color-primary-500',
      '--color-primary-600',
      '--color-primary-700',
      '--color-primary-800',
      '--color-primary-900',
    ],
  },
  {
    name: 'gray',
    vars: [
      '--color-gray-50',
      '--color-gray-100',
      '--color-gray-200',
      '--color-gray-300',
      '--color-gray-400',
      '--color-gray-500',
      '--color-gray-600',
      '--color-gray-700',
      '--color-gray-800',
      '--color-gray-900',
    ],
  },
  {
    name: 'secondary',
    vars: [
      '--color-secondary-50',
      '--color-secondary-100',
      '--color-secondary-200',
      '--color-secondary-300',
      '--color-secondary-400',
      '--color-secondary-500',
      '--color-secondary-600',
      '--color-secondary-700',
      '--color-secondary-800',
      '--color-secondary-900',
    ],
  },
] as const;

export default function FigmaCatalogPage() {
  if (process.env.NODE_ENV === 'production') {
    notFound();
  }

  return (
    <Container className="flex flex-col gap-10 py-12">
      <div>
        <h1 className="text-3xl font-bold">کاتالوگ توکن‌ها</h1>
        <p className="mt-2 text-sm text-gray-600">رنگ، دکمه و چیپ مطابق طرح تیره فروشگاه.</p>
      </div>
      {SCALES.map((scale) => (
        <section key={scale.name} className="flex flex-col gap-3">
          <h2 className="text-lg font-semibold">{scale.name}</h2>
          <div className="grid grid-cols-5 gap-2 md:grid-cols-10">
            {scale.vars.map((token) => (
              <div key={token} className="flex flex-col gap-1">
                <div className="h-14 rounded-lg" style={{ background: `var(${token})` }} />
                <span className="text-[10px] text-gray-500">{token.replace(`--color-${scale.name}-`, '')}</span>
              </div>
            ))}
          </div>
        </section>
      ))}
      <section className="flex flex-wrap gap-3">
        <Button>اصلی</Button>
        <Button variant="outline">حاشیه‌دار</Button>
        <Button variant="light">سطح تیره</Button>
        <Chip label="چیپ" />
        <Chip label="انتخاب‌شده" selected />
      </section>
    </Container>
  );
}
