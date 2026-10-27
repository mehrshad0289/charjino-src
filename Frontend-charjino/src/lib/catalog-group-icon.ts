const GROUP_ICON_SLUGS = [
  'game-topup',
  'gift-card',
  'game-keys',
  'steam-gifts',
  'steam-wallet',
  'telegram',
] as const;

const GROUP_ICON_SET = new Set<string>(GROUP_ICON_SLUGS);

export function catalogGroupIconSrc(slug?: string | null) {
  const id = slug?.trim().toLowerCase() ?? '';
  if (!GROUP_ICON_SET.has(id)) return null;
  return `/icons/charzhino-icons-flat/svg/${id}.svg`;
}
