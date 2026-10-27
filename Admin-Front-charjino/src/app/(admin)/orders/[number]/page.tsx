import { getOrder } from '@/actions/admin.actions';
import { OrderDetailScreen } from '@/components/admin/order-detail-screen';
import { Alert, ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

type OrderDetailPageProps = {
  params: Promise<{ number: string }>;
};

export async function generateMetadata({ params }: OrderDetailPageProps): Promise<Metadata> {
  const { number } = await params;
  return { title: `سفارش ${number}` };
}

export default async function AdminOrderDetailPage({ params }: OrderDetailPageProps) {
  const { number } = await params;
  const result = await getOrder(number);

  if (!result.ok) {
    return (
      <div>
        <PageBreadcrumb pageTitle='جزئیات سفارش' />
        <ComponentCard title='سفارش'>
          <Alert variant='error' title='سفارش خوانده نشد' message={result.message} />
        </ComponentCard>
      </div>
    );
  }

  return (
    <div>
      <PageBreadcrumb pageTitle={`سفارش ${result.item.number}`} />
      <ComponentCard
        title={result.item.productTitle || result.item.number}
        desc='بازیکن، بسته، پرداخت، تأمین و خط زمان از API واقعی'
      >
        <OrderDetailScreen order={result.item} />
      </ComponentCard>
    </div>
  );
}
