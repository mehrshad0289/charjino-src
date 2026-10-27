import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { getBlogPreview } from '@/actions/blog.actions';
import Link from 'next/link';
import { BlogHtml } from '@/components/blog/blog-html';
import { BlogRelatedProducts } from '@/components/blog/blog-related-products';
import { BlogTocNav } from '@/components/blog/blog-toc';
import { CatalogCover } from '@/components/catalog/catalog-cover';
import { JsonLd } from '@/components/seo/json-ld';
import { Container } from '@/components/ui';
import { faqPageJsonLd } from '@/lib/blog-schema';
import { prepareBlogHtml } from '@/lib/blog-toc';
import { toFaDigits } from '@/lib/fa';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const dynamic = 'force-dynamic';

type PageProps = {
  params: Promise<{ token: string }>;
};

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { token } = await params;
  const result = await getBlogPreview(token);
  if (!result.ok) {
    return buildMetadata({
      title: 'پیش‌نمایش مطلب',
      description: PAGE_DESCRIPTIONS.blog,
      path: `/blog/preview/${token}`,
      noindex: true,
    });
  }
  const post = result.data.post;
  return buildMetadata({
    title: post.metaTitle || `${post.title} | پیش‌نمایش`,
    description: post.metaDescription || post.excerpt || PAGE_DESCRIPTIONS.blog,
    path: `/blog/preview/${token}`,
    noindex: true,
    follow: false,
  });
}

export default async function BlogPreviewPage({ params }: PageProps) {
  const { token } = await params;
  const result = await getBlogPreview(token);
  if (!result.ok) {
    if ('notFound' in result && result.notFound) notFound();
    return (
      <div className="bg-surface">
        <Container className="py-12 text-sm text-text/70">{result.message}</Container>
      </div>
    );
  }
  const { post, products = [] } = result.data;
  const prepared = prepareBlogHtml(post.content);
  const reading = post.readingMinutes || 1;
  const faqLd = faqPageJsonLd(post.faq ?? []);

  return (
    <div className="bg-surface">
      {faqLd ? <JsonLd data={[faqLd]} /> : null}
      <Container className="flex flex-col gap-10 py-8 sm:py-12">
        <p className="rounded-2xl bg-card p-3 text-sm text-text/75 ring-1 ring-border">
          این پیش‌نمایش پیش‌نویس است و در فهرست عمومی وبلاگ دیده نمی‌شود.
        </p>
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
          <p className="text-sm text-text/55">{toFaDigits(reading)} دقیقه مطالعه</p>
          {post.excerpt ? <p className="text-sm leading-7 text-text/80">{post.excerpt}</p> : null}
          <BlogTocNav items={prepared.toc} />
          <BlogHtml html={prepared.html} />
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
        </article>
        <BlogRelatedProducts products={products} />
      </Container>
    </div>
  );
}
