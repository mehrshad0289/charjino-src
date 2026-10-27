const HTML_COMMENT = /<!--[\s\S]*?-->/g;
const HTML_TAG = /<\/?[a-zA-Z][^>]*>/g;
const MD_FENCE = /```[\s\S]*?```/g;
const MD_IMAGE = /!\[[^\]]*]\([^)]*\)/g;
const MD_LINK = /\[([^\]]*)]\([^)]*\)/g;
const MD_HEADING = /^#{1,6}\s+/gm;
const MD_QUOTE = /^>\s?/gm;
const MD_INLINE = /(`{1,3}|\*{1,3}|_{1,3}|~~)(.+?)\1/g;
const MD_TABLE_ROW = /^\s*\|.*\|\s*$/gm;
const WHITESPACE = /\s+/g;

function decodeHtmlEntities(value: string) {
  return value
    .replace(/&#x([0-9a-f]+);/gi, (_, hex: string) => {
      const code = Number.parseInt(hex, 16);
      return Number.isFinite(code) ? String.fromCodePoint(code) : _;
    })
    .replace(/&#(\d+);/g, (_, dec: string) => {
      const code = Number.parseInt(dec, 10);
      return Number.isFinite(code) ? String.fromCodePoint(code) : _;
    })
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&quot;/gi, '"')
    .replace(/&apos;/gi, "'")
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>');
}

export function toPlainText(value: string): string {
  return decodeHtmlEntities(value)
    .replace(HTML_COMMENT, ' ')
    .replace(HTML_TAG, ' ')
    .replace(MD_FENCE, ' ')
    .replace(MD_IMAGE, ' ')
    .replace(MD_LINK, '$1')
    .replace(MD_HEADING, '')
    .replace(MD_QUOTE, '')
    .replace(MD_INLINE, '$2')
    .replace(MD_TABLE_ROW, (row) => row.replace(/\|/g, ' '))
    .replace(WHITESPACE, ' ')
    .trim()
    .replace(/چارژینو/g, 'شارژینو');
}
