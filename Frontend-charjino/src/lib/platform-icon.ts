import { GAME_KEY_PLATFORM_ALL } from '@/lib/catalog-family';

const ICON_FILES = {
  all: 'all.svg',
  pc: 'all.svg',
  steam: 'steam.svg',
  playstation: 'playstation.svg',
  xbox: 'xbox.svg',
  ea: 'ea.svg',
  rockstar: 'rockstar.svg',
} as const;

export type PlatformIconId = keyof typeof ICON_FILES;

const ALIASES: Record<Exclude<PlatformIconId, 'all'>, string[]> = {
  steam: ['steam', 'steamworks', 'steam deck'],
  playstation: [
    'playstation',
    'playstation 3',
    'playstation 4',
    'playstation 5',
    'playstation network',
    'ps',
    'ps3',
    'ps4',
    'ps5',
    'psn',
    'sony',
  ],
  xbox: [
    'xbox',
    'xbox 360',
    'xbox one',
    'xbox series',
    'xbox series x',
    'xbox series s',
    'microsoft store',
    'ms store',
  ],
  ea: ['ea', 'ea app', 'origin', 'ea desktop', 'electronic arts'],
  rockstar: ['rockstar', 'rockstar games', 'rockstar games launcher', 'social club', 'rgl'],
  pc: ['pc', 'windows', 'win', 'computer'],
};

export function canonicalPlatformIconId(raw?: string | null): PlatformIconId {
  const needle = (raw ?? '').trim().toLowerCase();
  if (!needle || needle === GAME_KEY_PLATFORM_ALL || needle === 'همه') return 'all';
  for (const [id, aliases] of Object.entries(ALIASES) as [Exclude<PlatformIconId, 'all'>, string[]][]) {
    if (aliases.includes(needle)) return id;
  }
  if (needle.includes('playstation') || /\bps[345]?\b/.test(needle) || needle === 'psn') {
    return 'playstation';
  }
  if (needle.includes('xbox')) return 'xbox';
  if (needle === 'ea' || needle.startsWith('ea ') || needle.includes('origin')) return 'ea';
  if (needle.includes('rockstar') || needle.includes('social club')) return 'rockstar';
  if (needle.includes('steam')) return 'steam';
  if (needle === 'pc' || needle === 'windows' || needle === 'win') return 'pc';
  return 'all';
}

export function platformIconSrc(platform?: string | null) {
  const id = canonicalPlatformIconId(platform);
  return `/icons/platforms/${ICON_FILES[id]}`;
}
