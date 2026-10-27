import type { NextConfig } from 'next';

const PRODUCTION_API_ORIGIN = 'https://api.charjino.ir';

function stripApiSuffix(raw: string) {
  return raw.replace(/\/+$/, '').replace(/\/api$/i, '');
}

function backendOrigin() {
  return stripApiSuffix(process.env.BACKEND_URL || 'http://127.0.0.1:3000/api');
}

function storageOrigin() {
  const explicit = (process.env.STORAGE_ORIGIN || '').trim();
  if (explicit) return stripApiSuffix(explicit);
  const origin = backendOrigin();
  try {
    const hostname = new URL(origin).hostname;
    if (
      process.env.NODE_ENV === 'production' &&
      (hostname === '127.0.0.1' || hostname === 'localhost')
    ) {
      return PRODUCTION_API_ORIGIN;
    }
  } catch {
    return origin;
  }
  return origin;
}

function storageImagePattern() {
  try {
    const origin = new URL(backendOrigin());
    return {
      protocol: origin.protocol.replace(':', '') as 'http' | 'https',
      hostname: origin.hostname,
      ...(origin.port ? { port: origin.port } : {}),
      pathname: '/storage/**' as const,
    };
  } catch {
    return {
      protocol: 'http' as const,
      hostname: '127.0.0.1',
      port: '3000',
      pathname: '/storage/**' as const,
    };
  }
}

const nextConfig: NextConfig = {
  reactCompiler: true,
  poweredByHeader: false,
  compress: false,
  staticPageGenerationTimeout: 180,
  htmlLimitedBots:
    /Googlebot|[\w-]+-Google|Google-[\w-]+|Slurp|DuckDuckBot|baiduspider|yandex|sogou|bitlybot|tumblr|vkShare|quora link preview|redditbot|ia_archiver|Bingbot|BingPreview|applebot|facebookexternalhit|facebookcatalog|Twitterbot|LinkedInBot|Slackbot|Discordbot|WhatsApp|SkypeUriPreview|Yeti|googleweblight/,
  experimental: {
    serverActions: {
      bodySizeLimit: '64mb',
    },
    proxyClientMaxBodySize: '64mb',
    staticGenerationMaxConcurrency: 3,
    optimizePackageImports: ['swiper', 'iconsax-react', 'date-fns', 'date-fns-jalali'],
  },
  async headers() {
    const catalogCache = {
      key: 'Cache-Control',
      value: 'public, s-maxage=3600, stale-while-revalidate=86400',
    };
    const security = [
      { key: 'X-Frame-Options', value: 'SAMEORIGIN' },
      { key: 'X-Content-Type-Options', value: 'nosniff' },
      {
        key: 'Content-Security-Policy-Report-Only',
        value: [
          "default-src 'self'",
          "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.googletagmanager.com https://www.google-analytics.com *.contentsquare.net app.contentsquare.com",
          "style-src 'self' 'unsafe-inline'",
          "img-src 'self' data: blob: https: *.contentsquare.net",
          "font-src 'self' data:",
          "connect-src 'self' https://api.charjino.ir https://www.google-analytics.com https://www.googletagmanager.com https://www.googleapis.com *.contentsquare.net *.contentsquare.com",
          "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://trustseal.enamad.ir",
          "frame-ancestors 'self'",
          "base-uri 'self'",
          "form-action 'self'",
        ].join('; '),
      },
    ];
    return [
      { source: '/:path*', headers: security },
      { source: '/', headers: [catalogCache, ...security] },
      { source: '/catalog/:path*', headers: [catalogCache, ...security] },
      {
        source: '/storage/banners/:path*',
        headers: [{ key: 'Cache-Control', value: 'public, max-age=31536000, immutable' }],
      },
      {
        source: '/_next/image',
        headers: [{ key: 'Cache-Control', value: 'public, max-age=31536000, immutable' }],
      },
    ];
  },
  async redirects() {
    return [
      {
        source: '/blog/iran-game-topup-methods',
        destination: '/blog/game-topup-iran',
        permanent: true,
      },
    ];
  },
  async rewrites() {
    return [
      {
        source: '/sitemap/:id.xml',
        destination: '/sitemaps/:id',
      },
      {
        source: '/storage/:path*',
        destination: `${storageOrigin()}/storage/:path*`,
      },
    ];
  },
  images: {
    minimumCacheTTL: 31536000,
    formats: ['image/avif', 'image/webp'],
    localPatterns: [
      { pathname: '/storage/**' },
      { pathname: '/image/**' },
      { pathname: '/images/**' },
      { pathname: '/api/steam-cover/**' },
      { pathname: '/*' },
      { pathname: '/fonts/**' },
    ],
    remotePatterns: [
      storageImagePattern(),
      { protocol: 'https', hostname: 'reseller.fazercards.com' },
      { protocol: 'https', hostname: 'cdn.cloudflare.steamstatic.com' },
      { protocol: 'https', hostname: 'shared.akamai.steamstatic.com' },
      { protocol: 'https', hostname: 'cdn.akamai.steamstatic.com' },
      { protocol: 'https', hostname: 'clan.akamai.steamstatic.com' },
      { protocol: 'https', hostname: 'steamcdn-a.akamaihd.net' },
      { protocol: 'https', hostname: 'api.charjino.ir', pathname: '/storage/**' },
      { protocol: 'https', hostname: 'charjino.ir', pathname: '/storage/**' },
      { protocol: 'http', hostname: '127.0.0.1', port: '3000', pathname: '/storage/**' },
      { protocol: 'http', hostname: 'localhost', port: '3000', pathname: '/storage/**' },
    ],
  },
};

export default nextConfig;
