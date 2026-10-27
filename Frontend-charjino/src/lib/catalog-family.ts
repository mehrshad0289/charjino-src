export const GAME_KEYS_SLUG = 'game-keys';

export const GIFT_CARD_SLUG = 'gift-card';

export const GAME_KEY_PLATFORM_ALL = 'all';

export const BROWSE_PER_PAGE = 24;

const LABELS: Record<string, string> = {
  pc: 'PC',
  steam: 'Steam',
  playstation: 'پلی‌استیشن',
  xbox: 'ایکس‌باکس',
  nintendo: 'نینتندو',
  ea: 'EA',
  epic: 'Epic',
  ubisoft: 'Ubisoft',
  rockstar: 'Rockstar',
  battlenet: 'Battle.net',
  gog: 'GOG',
  itunes: 'App Store',
  'google-play': 'Google Play',
  'razer-gold': 'Razer Gold',
  'league-of-legends': 'League of Legends',
  'wild-rift': 'Wild Rift',
  'pubg-mobile': 'PUBG Mobile',
  'free-fire': 'Free Fire',
  amazon: 'Amazon',
  netflix: 'Netflix',
  roblox: 'Roblox',
  fortnite: 'Fortnite',
  valorant: 'Valorant',
  riot: 'Riot',
  tiktok: 'TikTok',
  tinder: 'Tinder',
  twitch: 'Twitch',
  airbnb: 'Airbnb',
  discord: 'Discord',
  telegram: 'Telegram',
  eneba: 'Eneba',
  kinguin: 'Kinguin',
  astropay: 'AstroPay',
  netease: 'NetEase',
  garena: 'Garena',
  'apex-legends': 'Apex Legends',
  pubg: 'PUBG',
};

const CONSOLE_STOREFRONTS = new Set(['playstation', 'xbox', 'nintendo']);

const LAUNCHER_ALIASES: Record<string, string[]> = {
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
  nintendo: ['nintendo', 'nintendo switch', 'switch'],
  ea: ['ea', 'ea app', 'origin', 'ea desktop', 'electronic arts'],
  epic: ['epic', 'epic games', 'epic games store', 'egs'],
  ubisoft: ['ubisoft', 'ubisoft connect', 'uplay'],
  rockstar: ['rockstar', 'rockstar games', 'rockstar games launcher', 'social club', 'rgl'],
  battlenet: ['battlenet', 'battle.net', 'blizzard', 'battle net'],
  gog: ['gog', 'gog.com'],
  pc: ['pc', 'windows', 'win', 'computer'],
};

export function gameKeyLauncherId(value?: string | null) {
  const needle = (value ?? '').trim().toLowerCase();
  if (!needle || needle === GAME_KEY_PLATFORM_ALL) return '';
  for (const [id, aliases] of Object.entries(LAUNCHER_ALIASES)) {
    if (aliases.includes(needle)) return id;
  }
  if (needle.includes('playstation') || /\bps[345]?\b/.test(needle) || needle === 'psn') return 'playstation';
  if (needle.includes('nintendo') || needle.includes('switch')) return 'nintendo';
  if (needle.includes('xbox')) return 'xbox';
  if (needle === 'ea' || needle.startsWith('ea ') || needle.includes('origin')) return 'ea';
  if (needle.includes('rockstar') || needle.includes('social club')) return 'rockstar';
  if (needle.includes('steam')) return 'steam';
  if (needle === 'pc' || needle === 'windows' || needle === 'win') return 'pc';
  return needle.replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
}

export function gameKeyStorefrontId(value?: string | null) {
  const launcher = gameKeyLauncherId(value);
  if (!launcher) return '';
  if (CONSOLE_STOREFRONTS.has(launcher)) return launcher;
  return 'pc';
}

export function gameKeyCardPlatformLabel(value?: string | null) {
  const launcher = gameKeyLauncherId(value) || value?.trim().toLowerCase() || '';
  if (!launcher) return '';
  const storefront = gameKeyStorefrontId(launcher);
  const storefrontLabel = gameKeyPlatformLabel(storefront);
  const launcherLabel = gameKeyPlatformLabel(launcher);
  if (!launcherLabel || launcherLabel === storefrontLabel || storefront === launcher) {
    return storefrontLabel || launcherLabel;
  }
  return `${storefrontLabel} · ${launcherLabel}`;
}

export function gameKeyPlatformLabel(value?: string | null) {
  const raw = value?.trim() ?? '';
  if (!raw) return '';
  const id = raw.toLowerCase();
  if (LABELS[id]) return LABELS[id];
  return id
    .split(/[\s-]+/)
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

export function catalogSubcategoryLabel(category: string) {
  if (category === GAME_KEYS_SLUG) return 'پلتفرم';
  if (category === GIFT_CARD_SLUG) return 'برند';
  return 'زیردسته';
}

export function resolveGameKeyBrowsePlatform(_category: string, platform: string) {
  if (platform === GAME_KEY_PLATFORM_ALL) return '';
  return platform.trim();
}

export function isGameKeyProduct(product: {
  fulfillmentType?: string | null;
  category?: { slug?: string | null } | null;
} | null | undefined) {
  if (!product) return false;
  return product.fulfillmentType === 'gamekey' || product.category?.slug === GAME_KEYS_SLUG;
}
