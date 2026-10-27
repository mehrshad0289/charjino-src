import { listBlogPosts } from '@/actions/blog.actions';
import { ROUTES } from '@/lib/routes';
import { BRAND_FA, absoluteUrl } from '@/lib/site';
import { toPlainText } from '@/lib/to-plain-text';

export const revalidate = 600;
export const dynamic = 'force-dynamic';

function escapeXml(value: string) {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

export async function GET() {
  const result = await listBlogPosts({ page: 1 });
  const items = result.ok ? result.data.items.slice(0, 20) : [];
  const body = items
    .map((post) => {
      const link = absoluteUrl(ROUTES.blogPost(post.slug));
      const desc = escapeXml(toPlainText(post.excerpt || ''));
      return `  <item>
    <title>${escapeXml(post.title)}</title>
    <link>${escapeXml(link)}</link>
    <guid>${escapeXml(link)}</guid>
    ${post.publishedAt ? `<pubDate>${escapeXml(new Date(post.publishedAt).toUTCString())}</pubDate>` : ''}
    <description>${desc}</description>
  </item>`;
    })
    .join('\n');
  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
<channel>
  <title>${escapeXml(`وبلاگ ${BRAND_FA}`)}</title>
  <link>${escapeXml(absoluteUrl(ROUTES.blog))}</link>
  <description>${escapeXml('آموزش و راهنمای خرید شارژ بازی و گیفت کارت')}</description>
  <language>fa-IR</language>
${body}
</channel>
</rss>
`;
  return new Response(xml, {
    status: 200,
    headers: {
      'Content-Type': 'application/rss+xml; charset=utf-8',
      'Cache-Control': 'public, s-maxage=600',
    },
  });
}
