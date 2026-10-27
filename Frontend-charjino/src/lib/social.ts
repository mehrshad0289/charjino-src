export const SOCIAL = {
  instagram: {
    title: 'اینستاگرام',
    handle: '@charjino_24',
    href: 'https://www.instagram.com/charjino_24/',
  },
  telegram: {
    title: 'تلگرام',
    handle: '@charjino_24',
    href: 'https://t.me/charjino_24',
  },
} as const;

export function telegramHrefWithText(text: string) {
  const trimmed = text.trim();
  if (!trimmed) {
    return SOCIAL.telegram.href;
  }
  try {
    const url = new URL(SOCIAL.telegram.href);
    url.searchParams.set('text', trimmed);
    return url.toString();
  } catch {
    const join = SOCIAL.telegram.href.includes('?') ? '&' : '?';
    return `${SOCIAL.telegram.href}${join}text=${encodeURIComponent(trimmed)}`;
  }
}
