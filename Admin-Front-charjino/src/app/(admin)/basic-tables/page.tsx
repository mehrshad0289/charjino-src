import { BasicTableOne } from '@/components/tables';
import { ComponentCard } from '@/components/ui/component-card';
import { PageBreadcrumb } from '@/components/ui/page-breadcrumb';
import { BASIC_TABLE_COPY } from '@/data/basic-table';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: BASIC_TABLE_COPY.pageTitle
};

export default function BasicTablesPage() {
  return (
    <div>
      <PageBreadcrumb pageTitle={BASIC_TABLE_COPY.pageTitle} />
      <div className='space-y-6'>
        <ComponentCard title={BASIC_TABLE_COPY.cardTitle}>
          <BasicTableOne />
        </ComponentCard>
      </div>
    </div>
  );
}
