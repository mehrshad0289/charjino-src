import './globals.css';

import { ThemeScript } from '@/components/ui/theme-script';
import { AppToaster } from '@/components/ui';
import { SidebarProvider } from '@/hooks/useSidebar';
import { ThemeProvider } from '@/hooks/useTheme';
import type { Metadata, Viewport } from 'next';

export const viewport: Viewport = {
  themeColor: [
    { media: '(prefers-color-scheme: light)', color: '#FFFFFF' },
    { media: '(prefers-color-scheme: dark)', color: '#0A0A0A' },
  ],
};

export const metadata: Metadata = {
  applicationName: 'پنل مدیریت شارژینو',
  title: {
    default: 'پنل مدیریت | شارژینو',
    template: '%s | پنل مدیریت شارژینو'
  },
  description: 'پنل مدیریت شارژینو',
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
  robots: { index: false, follow: false },
};

export default function RootLayout({
  children
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang='fa' dir='rtl' suppressHydrationWarning>
      <head>
        <ThemeScript />
      </head>
      <body className='font-outfit dark:bg-gray-900'>
        <ThemeProvider>
          <SidebarProvider>{children}</SidebarProvider>
          <AppToaster />
        </ThemeProvider>
      </body>
    </html>
  );
}
