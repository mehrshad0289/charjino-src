const LABELS: Record<string, string> = {
  pc: 'PC',
  steam: 'Steam',
  playstation: 'PlayStation',
  xbox: 'Xbox',
  nintendo: 'Nintendo',
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
  pubg: 'PUBG'
};

export const GIFT_PLATFORM_OPTIONS = Object.entries(LABELS).map(([value, label]) => ({ value, label }));

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
