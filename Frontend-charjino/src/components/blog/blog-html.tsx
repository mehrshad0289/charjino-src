import parse, { Element, type DOMNode, type HTMLReactParserOptions, domToReact } from 'html-react-parser';
import Image from 'next/image';
import Link from 'next/link';
import type { ReactElement } from 'react';
import { catalogImageSrc } from '@/lib/catalog-image';
import { SITE_URL } from '@/lib/site';

type BlogHtmlProps = {
  html: string;
};

function isInternalHref(href: string) {
  if (href.startsWith('/') && !href.startsWith('//')) return true;
  try {
    const url = new URL(href);
    return url.origin === SITE_URL;
  } catch {
    return false;
  }
}

function internalPath(href: string) {
  if (href.startsWith('/') && !href.startsWith('//')) return href;
  try {
    const url = new URL(href);
    return `${url.pathname}${url.search}${url.hash}`;
  } catch {
    return href;
  }
}

const options: HTMLReactParserOptions = {
  replace(node) {
    if (!(node instanceof Element)) return;
    if (node.name === 'img') {
      const src = String(node.attribs.src || '');
      if (!src) return <></>;
      const alt = String(node.attribs.alt || '');
      const width = Number.parseInt(node.attribs.width || '800', 10) || 800;
      const height = Number.parseInt(node.attribs.height || '450', 10) || 450;
      return (
        <Image
          src={catalogImageSrc(src)}
          alt={alt}
          width={width}
          height={height}
          sizes="(min-width: 1024px) 720px, 100vw"
          className="h-auto w-full rounded-2xl object-cover"
        />
      );
    }
    if (node.name === 'a') {
      const href = String(node.attribs.href || '');
      const children = node.children as unknown as DOMNode[];
      if (!href) return <span>{domToReact(children, options)}</span>;
      if (isInternalHref(href)) {
        return (
          <Link href={internalPath(href)} className="text-primary-600">
            {domToReact(children, options)}
          </Link>
        );
      }
      const rel = [node.attribs.rel, 'noopener', 'noreferrer'].filter(Boolean).join(' ');
      return (
        <a href={href} rel={rel} target={node.attribs.target || undefined} className="text-primary-600">
          {domToReact(children, options)}
        </a>
      );
    }
    return undefined;
  },
};

export function BlogHtml({ html }: BlogHtmlProps): ReactElement {
  return (
    <div className="blog-content space-y-4 text-sm leading-8 text-text [&_blockquote]:border-s-2 [&_blockquote]:border-primary-600 [&_blockquote]:ps-4 [&_h2]:scroll-mt-24 [&_h2]:text-xl [&_h2]:font-bold [&_h3]:scroll-mt-24 [&_h3]:text-lg [&_h3]:font-bold [&_li]:ms-5 [&_ol]:list-decimal [&_table]:w-full [&_table]:text-sm [&_td]:border [&_td]:border-border [&_td]:p-2 [&_th]:border [&_th]:border-border [&_th]:p-2 [&_ul]:list-disc">
      {parse(html || '<p></p>', options)}
    </div>
  );
}
