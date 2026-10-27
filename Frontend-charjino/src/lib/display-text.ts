import type { ReactNode } from 'react';
import { createElement } from 'react';

function compact(value: string) {
  return value.replace(/\s+/g, ' ').trim();
}

export function normalizeVisibleText(value: string) {
  return compact(value)
    .replace(/پلی\s*استیشن/g, 'پلی‌استیشن')
    .replace(/\s*\(\s*/g, ' (')
    .replace(/\s*\)\s*/g, ') ')
    .replace(/\s+/g, ' ')
    .trim();
}

const LEADING_EMOJI = /^(\p{Extended_Pictographic}(?:\uFE0F)?)\s*/u;

export function splitLeadingEmoji(value: string): { emoji: string; text: string } {
  const raw = compact(value);
  const match = raw.match(LEADING_EMOJI);
  if (!match) {
    return { emoji: '', text: normalizeVisibleText(raw) };
  }
  return {
    emoji: match[1],
    text: normalizeVisibleText(raw.slice(match[0].length)),
  };
}

export function headingChildren(value: string): ReactNode[] {
  const { emoji, text } = splitLeadingEmoji(value);
  const nodes: ReactNode[] = [];
  if (emoji) {
    nodes.push(createElement('span', { key: 'emoji', 'aria-hidden': true }, `${emoji} `));
  }
  if (text) {
    nodes.push(text);
  }
  return nodes;
}
