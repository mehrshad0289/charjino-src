import { getSearchHealth, listSearchSynonyms, listSearchTags } from '@/actions/admin.actions';
import { SearchSynonymsScreen, SearchTagsScreen } from '@/components/admin';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'جستجو و مترادف‌ها',
};

export default async function SearchSynonymsPage() {
  const [synonyms, health, tags] = await Promise.all([
    listSearchSynonyms(),
    getSearchHealth(),
    listSearchTags(),
  ]);

  return (
    <div>
      <PageBreadcrumb pageTitle="جستجو و مترادف‌ها" />
      <ComponentCard title="مترادف جستجو" desc="این تنظیمات به ایندکس Meilisearch فرستاده می‌شوند و دادهٔ کاتالوگ را تغییر نمی‌دهند.">
        <SearchSynonymsScreen
          items={synonyms.items}
          health={health.ok ? health.data : undefined}
          error={synonyms.ok ? undefined : synonyms.message}
        />
      </ComponentCard>
      <ComponentCard title="برچسب و برند" desc="kind=brand برای پیشنهاد برند در سرچ است. حدس خودکار از عنوان محصول کافی نیست.">
        <SearchTagsScreen items={tags.items} />
      </ComponentCard>
    </div>
  );
}
