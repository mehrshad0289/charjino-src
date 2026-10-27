import type { Metadata } from 'next';
import { notFound, permanentRedirect } from 'next/navigation';
import { getSessionUser } from '@/actions/auth.actions';
import { getBlogPost, listBlogComments } from '@/actions/blog.actions';
import Link from 'next/link';
import { BlogLikeBar } from '@/components/blog/blog-article';
import { BlogCard } from '@/components/blog/blog-card';
import { BlogComments } from '@/components/blog/blog-comments';
import { BlogHtml } from '@/components/blog/blog-html';
import { BlogRelatedProducts } from '@/components/blog/blog-related-products';
import { BlogTocNav } from '@/components/blog/blog-toc';
import { CatalogCover } from '@/components/catalog/catalog-cover';
import { JsonLd } from '@/components/seo/json-ld';
import { Container } from '@/components/ui';
import { formatBlogJalali } from '@/lib/blog-dates';
import { blogPostingJsonLd, breadcrumbRecords, faqPageJsonLd } from '@/lib/blog-schema';
import { prepareBlogHtml } from '@/lib/blog-toc';
import { toFaDigits } from '@/lib/fa';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata, clipDescription } from '@/lib/seo';
import { absoluteUrl } from '@/lib/site';
import { toPlainText } from '@/lib/to-plain-text';

export const revalidate = 60;

type PageProps = {
  params: Promise<{ slug: string }>;
};

function coverAbsolute(src?: string | null) {
  if (!src) return undefined;
  return src.startsWith('/') ? absoluteUrl(src) : src;
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { slug } = await params;
  const result = await getBlogPost(slug);
  if (!result.ok) {
    return buildMetadata({
      title: 'مطلب وبلاگ',
      description: PAGE_DESCRIPTIONS.blog,
      path: ROUTES.blogPost(slug),
      noindex: true,
    });
  }
  const post = result.data.post;
  const path = post.canonicalUrl?.startsWith('http') ? undefined : ROUTES.blogPost(post.slug);
  const metadata = buildMetadata({
    title: post.metaTitle || `${post.title} | شارژینو`,
    description: clipDescription(post.metaDescription || post.excerpt || toPlainText(post.content)),
    path: path || ROUTES.blogPost(post.slug),
    image: coverAbsolute(post.ogImage || post.cover),
    noindex: Boolean(post.noindex),
    follow: true,
  });
  metadata.openGraph = {
    ...metadata.openGraph,
    type: 'article',
    title: post.ogTitle || metadata.openGraph?.title,
    description: post.ogDescription ? clipDescription(post.ogDescription) : metadata.openGraph?.description,
    publishedTime: post.publishedAt || undefined,
    modifiedTime: post.updatedAt || post.publishedAt || undefined,
  };
  metadata.twitter = {
    ...metadata.twitter,
    title: post.twitterTitle || post.ogTitle || metadata.twitter?.title,
    description: clipDescription(post.twitterDescription || post.ogDescription || post.metaDescription || post.excerpt || ''),
    images: coverAbsolute(post.twitterImage || post.ogImage || post.cover)
      ? [coverAbsolute(post.twitterImage || post.ogImage || post.cover) as string]
      : metadata.twitter?.images,
  };
  if (post.canonicalUrl?.startsWith('http')) {
    metadata.alternates = { canonical: post.canonicalUrl };
  }
  return metadata;
}

export default async function BlogPostPage({ params }: PageProps) {
  const { slug } = await params;
  const [result, user] = await Promise.all([getBlogPost(slug), getSessionUser()]);
  if (!result.ok) {
    if ('redirectTo' in result && result.redirectTo) permanentRedirect(result.redirectTo);
    if ('notFound' in result && result.notFound) notFound();
    return (
      <div className="bg-surface">
        <Container className="py-12 text-sm text-text/70">{result.message}</Container>
      </div>
    );
  }
  const { post, related, products = [] } = result.data;
  const comments = await listBlogComments(post.slug);
  const signedIn = Boolean(user);
  const prepared = prepareBlogHtml(post.content);
  const reading = post.readingMinutes || 1;
  const jsonLd = [
    breadcrumbRecords([
      { name: 'خانه', path: ROUTES.home },
      { name: 'وبلاگ', path: ROUTES.blog },
      ...(post.category ? [{ name: post.category.title, path: ROUTES.blogCategory(post.category.slug) }] : []),
      { name: post.title, path: ROUTES.blogPost(post.slug) },
    ]),
    blogPostingJsonLd(post),
  ];
  const faqLd = faqPageJsonLd(post.faq ?? []);
  if (faqLd) jsonLd.push(faqLd);

  return (
    <div className="bg-surface">
      <JsonLd data={jsonLd} />
      <Container className="flex flex-col gap-10 py-8 sm:py-12">
        {post.cover ? (
          <div className="relative aspect-[16/9] w-full overflow-hidden rounded-2xl bg-card-muted">
            <CatalogCover
              src={post.cover}
              alt={post.coverAlt || post.title}
              fill
              fit="cover"
              priority
              sizes="100vw"
            />
          </div>
        ) : null}
        <article className="flex flex-col gap-5">
          {post.category ? (
            <Link href={ROUTES.blogCategory(post.category.slug)} className="text-sm text-primary-600">
              {post.category.title}
            </Link>
          ) : null}
          <h1 className="text-2xl font-bold text-text sm:text-3xl">{post.title}</h1>
          <p className="flex flex-wrap gap-x-3 gap-y-1 text-sm text-text/55">
            {post.author?.slug ? (
              <Link href={ROUTES.blogAuthor(post.author.slug)} className="text-primary-600">
                {post.author.name}
              </Link>
            ) : (
              <span>{post.author?.name}</span>
            )}
            {post.publishedAt ? (
              <time dateTime={post.publishedAt}>انتشار {formatBlogJalali(post.publishedAt)}</time>
            ) : null}
            {post.updatedAt && post.updatedAt !== post.publishedAt ? (
              <time dateTime={post.updatedAt}>به‌روزرسانی {formatBlogJalali(post.updatedAt)}</time>
            ) : null}
            <span>{toFaDigits(reading)} دقیقه مطالعه</span>
          </p>
          {post.excerpt ? <p className="text-sm leading-7 text-text/80">{post.excerpt}</p> : null}
          <BlogTocNav items={prepared.toc} />
          <BlogHtml html={prepared.html} />
          {(post.tags ?? []).length > 0 ? (
            <p className="flex flex-wrap gap-2 text-sm">
              {(post.tags ?? []).map((tag) => (
                <Link key={tag.slug} href={ROUTES.blogTag(tag.slug)} className="text-primary-600">
                  #{tag.title}
                </Link>
              ))}
            </p>
          ) : null}
          {(post.faq ?? []).length > 0 ? (
            <section className="flex flex-col gap-3">
              <h2 className="text-xl font-bold text-text">سؤالات پرتکرار</h2>
              {(post.faq ?? []).map((item) => (
                <div key={item.question} className="rounded-2xl bg-card p-4 ring-1 ring-border">
                  <h3 className="text-sm font-bold text-text">{item.question}</h3>
                  <p className="mt-2 text-sm leading-7 text-text/75">{item.answer}</p>
                </div>
              ))}
            </section>
          ) : null}
          <BlogLikeBar post={post} signedIn={signedIn} />
        </article>
        <BlogRelatedProducts products={products} />
        <BlogComments slug={post.slug} signedIn={signedIn} initialItems={comments.ok ? comments.data.items : []} />
        {related.length > 0 ? (
          <section className="flex flex-col gap-4">
            <h2 className="text-lg font-bold text-text">مطالب مرتبط</h2>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {related.map((item) => (
                <BlogCard key={item.id} post={item} />
              ))}
            </div>
          </section>
        ) : null}
      </Container>
    </div>
  );
}
