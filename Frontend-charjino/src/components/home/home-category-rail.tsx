import { Chip } from '@/components/ui';
import { catalogGroupIconSrc } from '@/lib/catalog-group-icon';
import { categoryIconAlt } from '@/lib/image-alt';
import { catalogBrowseHref, ROUTES } from '@/lib/routes';
import type { CatalogGroup } from '@/types/catalog';
import { HomeMarquee } from './home-marquee';

type HomeCategoryRailProps = {
  groups: CatalogGroup[];
};

function groupHref(group: CatalogGroup) {
  const slug = group.slug?.trim();
  return slug ? catalogBrowseHref({ category: slug }) : ROUTES.categories;
}

function GroupIcon({ slug, label }: { slug?: string; label: string }) {
  const src = catalogGroupIconSrc(slug);
  if (!src) return null;
  return (
    <img
      src={src}
      width={36}
      height={36}
      alt={categoryIconAlt(label)}
      loading="lazy"
      decoding="async"
      fetchPriority="low"
      className="size-10 shrink-0 object-contain"
    />
  );
}

export function HomeCategoryRail({ groups }: HomeCategoryRailProps) {
  if (groups.length === 0) return null;

  return (
    // <HomeMarquee gap={12} minHeight="5.5rem">
    <div className="grid grid-cols-2 gap-4 my-3">
      {groups.map((group) => (
        <div key={group.slug ?? group.name} className='bg-linear-to-b from-[#00E5FF] p-px  rounded-sm'>
        <Chip
          key={group.slug ?? group.name}
          className="shrink-0 whitespace-nowrap "
          icon={<GroupIcon slug={group.slug} label={group.name} />}
          label={group.name}
          href={groupHref(group)}
        />
        </div>
      ))}
    </div>
    // </HomeMarquee>
  );
}
