import type { Metadata, Viewport } from 'next';
import { preload } from 'react-dom';
import { GoogleAnalytics } from '@/components/seo/google-analytics';
import { HotjarAnalytics } from '@/components/seo/hotjar';
import { JsonLd } from '@/components/seo/json-ld';
import { AppToaster } from '@/components/ui/toaster';
import { APP_NAME, APP_NAME_EN } from '@/lib/constants';
import { organizationWebsiteJsonLd } from '@/lib/json-ld';
import { HOME_TITLE, OG_IMAGE, OG_IMAGE_ALT, PAGE_DESCRIPTIONS, clipDescription } from '@/lib/seo';
import { SITE_URL } from '@/lib/site';
import './globals.css';

export const viewport: Viewport = {
  themeColor: '#171717',
  colorScheme: 'dark',
  width: 'device-width',
  initialScale: 1,
};

export const metadata: Metadata = {
  metadataBase: new URL(SITE_URL),
  applicationName: APP_NAME,
  title: {
    default: HOME_TITLE,
    template: `%s | ${APP_NAME}`,
  },
  description: clipDescription(PAGE_DESCRIPTIONS.home),
  robots: {
    googleBot: {
      'max-image-preview': 'large',
    },
  },
  verification: {
    google: 'uX3nnsobE4TzG4igrx4I-3ykDvqe26qdyKdyyJ53LbE',
  },
  icons: {
    icon: [
      { url: '/favicon-16x16.png', sizes: '16x16', type: 'image/png' },
      { url: '/favicon-32x32.png', sizes: '32x32', type: 'image/png' },
      { url: '/icon-192.png', sizes: '192x192', type: 'image/png' },
      { url: '/icon-512.png', sizes: '512x512', type: 'image/png' },
    ],
    apple: [{ url: '/apple-touch-icon.png', sizes: '180x180', type: 'image/png' }],
  },
  manifest: '/site.webmanifest',
  openGraph: {
    type: 'website',
    locale: 'fa_IR',
    siteName: `${APP_NAME} | ${APP_NAME_EN}`,
    title: HOME_TITLE,
    description: clipDescription(PAGE_DESCRIPTIONS.home),
    images: [{ url: OG_IMAGE, width: 1200, height: 630, alt: OG_IMAGE_ALT }],
  },
  twitter: {
    card: 'summary_large_image',
    title: HOME_TITLE,
    description: clipDescription(PAGE_DESCRIPTIONS.home),
    images: [{ url: '/twitter-image.png', width: 1200, height: 630, alt: OG_IMAGE_ALT }],
  },
  appleWebApp: {
    capable: true,
    statusBarStyle: 'black-translucent',
    title: APP_NAME,
  },
};

const themeBoot = `(function(){try{var t=localStorage.getItem('charjino-theme');if(t!=='light')t='dark';document.documentElement.setAttribute('data-theme',t);document.documentElement.style.colorScheme=t;}catch(e){}})();`;

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  preload('/fonts/YekanBakh-Regular.woff2', {
    as: 'font',
    type: 'font/woff2',
    crossOrigin: 'anonymous',
  });
  preload('/fonts/YekanBakh-Medium.woff2', {
    as: 'font',
    type: 'font/woff2',
    crossOrigin: 'anonymous',
  });
  preload('/fonts/YekanBakh-Bold.woff2', {
    as: 'font',
    type: 'font/woff2',
    crossOrigin: 'anonymous',
  });

  return (
    <html lang="fa" dir="rtl" data-theme="dark" suppressHydrationWarning>
      <body className="bg-background dark:bg-transparent font-sans text-text antialiased">
        <script dangerouslySetInnerHTML={{ __html: themeBoot }} />
        <JsonLd data={organizationWebsiteJsonLd()} />
        {children}
        <AppToaster />
        <GoogleAnalytics />
        <HotjarAnalytics />
      </body>
    </html>
  );
}
