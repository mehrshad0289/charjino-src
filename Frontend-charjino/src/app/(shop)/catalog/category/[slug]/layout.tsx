import { notFound } from 'next/navigation';
import type { ReactNode } from 'react';
import { getCatalogCategories } from '@/actions/catalog.actions';

type CategoryLayoutProps = {
  children: ReactNode;
  params: Promise<{ slug: string }>;
};

export default async function CategorySlugLayout({ children, params }: CategoryLayoutProps) {
  const { slug } = await params;
  const result = await getCatalogCategories();
  if (result.ok && !result.items.some((item) => item.slug === slug)) {
    notFound();
  }
  return children;
}
