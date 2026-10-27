import { getFazerCardsStatus } from '@/actions/admin.actions';
import { FazerCardsOpsScreen } from '@/components/admin/fazer-cards/fazer-cards-ops-screen';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import { fazerCardsSectionMeta, type FcOpsSection } from '@/lib/fazer-cards-nav';
import type { Metadata } from 'next';
import { notFound } from 'next/navigation';

const OPS: FcOpsSection[] = [
  'wallet',
  'subscription',
  'orders',
  'ledger',
  'topups',
  'giftcards',
  'gamekeys',
  'steam-topup',
  'steam-gifts',
  'telegram',
  'manual',
  'funpay',
  'webhooks'
];

type PageProps = {
  params: Promise<{ section: string }>;
};

export function generateStaticParams() {
  return OPS.map((section) => ({ section }));
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { section } = await params;
  const meta = fazerCardsSectionMeta(section);
  return { title: meta?.title || 'FazerCards' };
}

export default async function FazerCardsOpsPage({ params }: PageProps) {
  const { section } = await params;
  if (!OPS.includes(section as FcOpsSection)) {
    notFound();
  }
  const meta = fazerCardsSectionMeta(section);
  const result = await getFazerCardsStatus();

  return (
    <div>
      <PageBreadcrumb pageTitle={meta?.title || 'FazerCards'} />
      <ComponentCard title={meta?.title || 'FazerCards'} desc={meta?.desc}>
        <FazerCardsOpsScreen
          section={section as FcOpsSection}
          status={result.ok ? result.data : undefined}
          error={result.ok ? undefined : result.message}
        />
      </ComponentCard>
    </div>
  );
}
