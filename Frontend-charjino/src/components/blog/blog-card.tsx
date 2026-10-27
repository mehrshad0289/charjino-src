import Link from 'next/link';
import { CatalogCover } from '@/components/catalog/catalog-cover';
import { formatFaDate, toFaDigits } from '@/lib/fa';
import { ROUTES } from '@/lib/routes';
import type { BlogPostCard } from '@/types/blog';

type BlogCardProps = {
  post: BlogPostCard;
};

export function BlogCard({ post }: BlogCardProps) {
  return (
    <Link href={ROUTES.blogPost(post.slug)} className="block h-full">
      <article className="flex h-full flex-col overflow-hidden rounded-2xl bg-card shadow-card ring-1 ring-border transition-all duration-300 hover:ring-2 hover:ring-primary-600 hover:ring-offset-2 hover:ring-offset-background">
        <div className="relative aspect-[16/9] w-full bg-card-muted">
          {post.cover ? (
            <CatalogCover src={post.cover} alt={post.title} fill fit="cover" sizes="(min-width: 1024px) 30vw, 100vw" />
          ) : (
            <div className="absolute inset-0 bg-gradient-to-br from-primary-600/20 to-card-muted" />
          )}
        </div>
        <div className="flex flex-1 flex-col gap-2 px-4 py-3">
          {post.category ? (
            <p className="text-xs font-medium text-primary-600">{post.category.title}</p>
          ) : null}
          <h2 className="text-sm font-bold text-text">{post.title}</h2>
          {post.excerpt ? <p className="line-clamp-2 text-xs text-text/70">{post.excerpt}</p> : null}
          <p className="mt-auto text-[11px] text-text/50">
            {formatFaDate(post.publishedAt)}
            {post.commentsCount > 0 ? ` · ${toFaDigits(post.commentsCount)} نظر` : ''}
          </p>
        </div>
      </article>
    </Link>
  );
}
