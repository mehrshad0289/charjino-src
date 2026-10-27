import { DemographicCard } from './demographic-card';
import { EcommerceMetrics } from './ecommerce-metrics';
import { MonthlySalesChart } from './monthly-sales-chart';
import { MonthlyTarget } from './monthly-target';
import { RecentOrders } from './recent-orders';
import { StatisticsChart } from './statistics-chart';

export function EcommerceDashboard() {
  return (
    <div className='grid grid-cols-12 gap-4 md:gap-6'>
      <div className='col-span-12 space-y-6 xl:col-span-7'>
        <EcommerceMetrics />
        <MonthlySalesChart />
      </div>
      <div className='col-span-12 xl:col-span-5'>
        <MonthlyTarget />
      </div>
      <div className='col-span-12'>
        <StatisticsChart />
      </div>
      <div className='col-span-12 xl:col-span-5'>
        <DemographicCard />
      </div>
      <div className='col-span-12 xl:col-span-7'>
        <RecentOrders />
      </div>
    </div>
  );
}
