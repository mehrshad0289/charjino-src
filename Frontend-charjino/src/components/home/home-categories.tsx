import { Container } from '@/components/ui';
import type { CatalogGroup } from '@/types/catalog';
import { HomeCategoryRail } from './home-category-rail';

type HomeCategoriesProps = {
  groups: CatalogGroup[];
};

export function HomeCategories({ groups }: HomeCategoriesProps) {
  if (groups.length === 0) return null;

  return (
    <Container className="flex min-w-0 max-w-full flex-col gap-3 overflow-hidden">
      <div className="flex flex-col">
        <h2 className="text-lg font-bold tracking-wider uppercase leading-relaxed">
          دنبال چی می‌گردی؟
        </h2>
        <p className="text-sm text-text-muted">همه چیز در شارژینو؛ دسته را بزن و برو همان فروشگاه.</p>
      </div>
      <HomeCategoryRail groups={groups} />
    </Container>
  );
}
