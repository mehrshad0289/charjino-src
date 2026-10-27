'use client';

import { CHART_FONT, ECOMMERCE_COPY, ECOMMERCE_MONTHS } from '@/data/ecommerce';
import { ChartTab } from '@/components/ui/chart-tab';
import { DateField } from '@/components/ui/field';
import type { ApexOptions } from 'apexcharts';
import dynamic from 'next/dynamic';

const Chart = dynamic(() => import('react-apexcharts'), { ssr: false });

function isoDate(date: Date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function lastSevenDays() {
  const end = new Date();
  const start = new Date();
  start.setDate(end.getDate() - 6);
  return { start: isoDate(start), end: isoDate(end) };
}

const LAST_SEVEN_DAYS = lastSevenDays();

export function StatisticsChart() {
  const options: ApexOptions = {
    legend: { show: false, position: 'top', horizontalAlign: 'left' },
    colors: ['#465FFF', '#9CB9FF'],
    chart: {
      fontFamily: CHART_FONT,
      height: 310,
      type: 'line',
      toolbar: { show: false }
    },
    stroke: { curve: 'straight', width: [2, 2] },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.55, opacityTo: 0 } },
    markers: { size: 0, strokeColors: '#fff', strokeWidth: 2, hover: { size: 6 } },
    grid: {
      xaxis: { lines: { show: false } },
      yaxis: { lines: { show: true } }
    },
    dataLabels: { enabled: false },
    tooltip: { enabled: true, x: { format: 'dd MMM yyyy' } },
    xaxis: {
      type: 'category',
      categories: [...ECOMMERCE_MONTHS],
      axisBorder: { show: false },
      axisTicks: { show: false },
      tooltip: { enabled: false }
    },
    yaxis: {
      labels: { style: { fontSize: '12px', colors: ['#6B7280'] } },
      title: { text: '', style: { fontSize: '0px' } }
    }
  };
  const series = [
    { name: ECOMMERCE_COPY.sales, data: [180, 190, 170, 160, 175, 165, 170, 205, 230, 210, 240, 235] },
    { name: ECOMMERCE_COPY.revenue, data: [40, 30, 50, 40, 55, 40, 70, 100, 110, 120, 150, 140] }
  ];

  return (
    <div className='overflow-visible rounded-2xl border border-gray-200 bg-white px-5 pt-5 pb-5 sm:px-6 sm:pt-6 dark:border-gray-800 dark:bg-white/3'>
      <div className='relative z-20 mb-6 flex flex-col gap-5 sm:flex-row sm:justify-between'>
        <div className='w-full'>
          <h3 className='text-lg font-semibold text-gray-800 dark:text-white/90'>{ECOMMERCE_COPY.statistics}</h3>
          <p className='mt-1 text-theme-sm text-gray-500 dark:text-gray-400'>{ECOMMERCE_COPY.statisticsSubtitle}</p>
        </div>
        <div className='flex items-center gap-3 sm:justify-end'>
          <ChartTab />
          <DateField compact mode='range' placeholder={ECOMMERCE_COPY.selectDateRange} defaultRange={LAST_SEVEN_DAYS} />
        </div>
      </div>
      <div className='custom-scrollbar max-w-full overflow-x-auto'>
        <div className='min-w-250 xl:min-w-full'>
          <Chart options={options} series={series} type='area' height={310} />
        </div>
      </div>
    </div>
  );
}
