import type { BlogTocItem } from '@/lib/blog-toc';

type BlogTocNavProps = {
  items: BlogTocItem[];
};

export function BlogTocNav({ items }: BlogTocNavProps) {
  if (items.length === 0) return null;
  return (
    <nav aria-label="فهرست مطلب" className="rounded-2xl bg-card p-4 ring-1 ring-border">
      <p className="mb-3 text-sm font-bold text-text">فهرست</p>
      <ol className="flex flex-col gap-2 text-sm">
        {items.map((item) => (
          <li key={item.id} className={item.level === 3 ? 'ps-4' : ''}>
            <a href={`#${item.id}`} className="text-text/75 hover:text-primary-600">
              {item.text}
            </a>
          </li>
        ))}
      </ol>
    </nav>
  );
}
