import type { Metadata } from 'next';
import { LeaderboardScreen } from '@/components/leaderboard';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const metadata: Metadata = buildMetadata({
  title: 'جدول برترین‌ها',
  description: PAGE_DESCRIPTIONS.leaderboard,
  path: ROUTES.leaderboard,
  noindex: true,
});

export default function LeaderboardPage() {
  return <LeaderboardScreen />;
}
