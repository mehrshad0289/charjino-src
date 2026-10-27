import * as cheerio from 'cheerio';

export type BlogTocItem = {
  id: string;
  text: string;
  level: 2 | 3;
};

const ALLOWED_TAGS = new Set([
  'h2',
  'h3',
  'h4',
  'p',
  'br',
  'strong',
  'b',
  'em',
  'i',
  'u',
  'ul',
  'ol',
  'li',
  'blockquote',
  'a',
  'img',
  'table',
  'thead',
  'tbody',
  'tr',
  'th',
  'td',
  'figure',
  'figcaption',
  'iframe',
  'div',
  'code',
  'pre',
  'hr',
]);

const ALLOWED_ATTR = new Set([
  'href',
  'rel',
  'target',
  'src',
  'alt',
  'width',
  'height',
  'id',
  'dir',
  'class',
  'title',
  'colspan',
  'rowspan',
  'allowfullscreen',
]);

export function headingId(text: string, used: Set<string>) {
  const base =
    text
      .trim()
      .toLowerCase()
      .replace(/[^\p{L}\p{N}\s_-]+/gu, '')
      .replace(/[\s_]+/gu, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '')
      .slice(0, 80) || 'section';
  let id = base;
  let n = 2;
  while (used.has(id)) {
    id = `${base}-${n}`;
    n += 1;
  }
  used.add(id);
  return id;
}

export function prepareBlogHtml(html: string): { html: string; toc: BlogTocItem[] } {
  const $ = cheerio.load(html || '', null, false);
  $('script, style').remove();
  $('*').each((_, el) => {
    if (el.type !== 'tag') return;
    const node = $(el);
    if (!ALLOWED_TAGS.has(el.name)) {
      node.replaceWith(node.contents());
      return;
    }
    for (const attr of Object.keys(el.attribs ?? {})) {
      if (!ALLOWED_ATTR.has(attr.toLowerCase()) || attr.toLowerCase().startsWith('on')) {
        node.removeAttr(attr);
      }
    }
    const href = node.attr('href') ?? '';
    if (href.toLowerCase().startsWith('javascript:') || href.toLowerCase().startsWith('data:')) {
      node.removeAttr('href');
    }
  });
  const used = new Set<string>();
  const toc: BlogTocItem[] = [];
  $('h2, h3').each((_, el) => {
    const node = $(el);
    const text = node.text().trim();
    if (!text) return;
    const id = node.attr('id')?.trim() || headingId(text, used);
    node.attr('id', id);
    const tag = String($(el).prop('tagName') || '').toLowerCase();
    toc.push({ id, text, level: tag === 'h3' ? 3 : 2 });
  });
  return { html: $.html(), toc };
}
