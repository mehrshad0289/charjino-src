import { listAdminTickets } from '@/actions/tickets.actions';
import { SupportListScreen } from '@/components/support';
import { PageBreadcrumb } from '@/components/ui/page-breadcrumb';
import { SUPPORT_COPY, SUPPORT_PAGE_SIZE } from '@/data/support';
import { parseTablePage } from '@/lib/table-page';
import type { SupportTabId } from '@/types/support';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: SUPPORT_COPY.listTitle,
};

export const dynamic = 'force-dynamic';

type PageProps = {
  searchParams: Promise<{ status?: string; page?: string; q?: string }>;
};

function parseTab(status?: string): SupportTabId {
  if (status === 'open' || status === 'answered' || status === 'closed') return status;
  return 'all';
}

export default async function SupportListPage({ searchParams }: PageProps) {
  const params = await searchParams;
  const tab = parseTab(params.status);
  const page = parseTablePage(params.page);
  const q = params.q?.trim();

  const listResult = await listAdminTickets({
    status: tab === 'all' ? undefined : tab,
    page,
    perPage: SUPPORT_PAGE_SIZE,
    q,
  });

  const [openRes, answeredRes, closedRes] = await Promise.all([
    listAdminTickets({ status: 'open', page: 1, perPage: 1 }),
    listAdminTickets({ status: 'answered', page: 1, perPage: 1 }),
    listAdminTickets({ status: 'closed', page: 1, perPage: 1 }),
  ]);

  const total = listResult.ok ? listResult.meta.total : 0;
  const totalPages = listResult.ok ? listResult.meta.totalPages : 1;

  const metrics = {
    total:
      (openRes.ok ? openRes.meta.total : 0) +
      (answeredRes.ok ? answeredRes.meta.total : 0) +
      (closedRes.ok ? closedRes.meta.total : 0),
    open: openRes.ok ? openRes.meta.total : 0,
    answered: answeredRes.ok ? answeredRes.meta.total : 0,
    closed: closedRes.ok ? closedRes.meta.total : 0,
  };

  return (
    <div>
      <PageBreadcrumb pageTitle={SUPPORT_COPY.listTitle} />
      <SupportListScreen
        items={listResult.ok ? listResult.items : []}
        error={listResult.ok ? undefined : listResult.message}
        tab={tab}
        page={listResult.ok ? listResult.meta.page : page}
        totalPages={totalPages}
        total={total}
        metrics={metrics}
      />
    </div>
  );
}
