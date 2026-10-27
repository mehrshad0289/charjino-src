import { SupportOrderBox } from '@/components/checkout/support-order-box';

type DryRunNoticeProps = {
  liveOrderEnabled?: boolean;
  productTitle?: string | null;
  pagePath?: string | null;
};

export function DryRunNotice({ liveOrderEnabled, productTitle, pagePath }: DryRunNoticeProps) {
  if (liveOrderEnabled) return null;

  return <SupportOrderBox productTitle={productTitle} pagePath={pagePath} />;
}
