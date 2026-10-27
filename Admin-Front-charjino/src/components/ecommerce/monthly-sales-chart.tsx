'use client';

import { COMMON_COPY, CHART_FONT, ECOMMERCE_COPY, ECOMMERCE_MONTHS } from '@/data/ecommerce';
import { Dropdown, DropdownItem } from '@/components/ui/dropdown';
import { MoreDotIcon } from '@/components/ui/icon';
import type { ApexOptions } from 'apexcharts';
import dynamic from 'next/dynamic';
import { useState } from 'react';

const ReactApexChart = dynamic(() => import('react-apexcharts'), { ssr: false });

export function MonthlySalesChart() {
  const [isOpen, setIsOpen] = useState(false);
  const options: ApexOptions = {
    colors: ['#465fff'],
    chart: {
      fontFamily: CHART_FONT,
      type: 'bar',
      height: 180,
      toolbar: { show: false }
    },
    plotOptions: {
      bar: {
        horizontal: false,
        columnWidth: '39%',
        borderRadius: 5,
        borderRadiusApplication: 'end'
      }
    },
    dataLabels: { enabled: false },
    stroke: { show: true, width: 4, colors: ['transparent'] },
    xaxis: {
      categories: [...ECOMMERCE_MONTHS],
      axisBorder: { show: false },
      axisTicks: { show: false },
      tickPlacement: 'on'
    },
    legend: {
      show: true,
      position: 'top',
      horizontalAlign: 'left',
      fontFamily: CHART_FONT
    },
    yaxis: { title: { text: undefined } },
    grid: { yaxis: { lines: { show: true } } },
    fill: { opacity: 1 },
    states: {
      hover: { filter: { type: 'none' } },
      active: { filter: { type: 'none' } }
    },
    tooltip: {
      x: { show: false },
      y: { formatter: (val: number) => `${val}` }
    }
  };
  const series = [{ name: ECOMMERCE_COPY.sales, data: [168, 385, 201, 298, 187, 195, 291, 110, 215, 390, 280, 112] }];

  return (
    <div className='overflow-hidden rounded-2xl border border-gray-200 bg-white px-5 pt-5 sm:px-6 sm:pt-6 dark:border-gray-800 dark:bg-white/3'>
      <div className='flex items-center justify-between'>
        <h3 className='text-lg font-semibold text-gray-800 dark:text-white/90'>{ECOMMERCE_COPY.monthlySales}</h3>
        <div className='relative h-fit'>
          <button type='button' onClick={() => setIsOpen(!isOpen)} className='dropdown-toggle'>
            <MoreDotIcon className='text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' />
          </button>
          <Dropdown isOpen={isOpen} onClose={() => setIsOpen(false)} className='w-40 p-2'>
            <DropdownItem
              onItemClick={() => setIsOpen(false)}
              className='flex w-full rounded-lg text-start font-normal text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300'
            >
              {COMMON_COPY.viewMore}
            </DropdownItem>
            <DropdownItem
              onItemClick={() => setIsOpen(false)}
              className='flex w-full rounded-lg text-start font-normal text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300'
            >
              {COMMON_COPY.delete}
            </DropdownItem>
          </Dropdown>
        </div>
      </div>
      <div className='custom-scrollbar max-w-full overflow-x-auto'>
        <div className='min-w-162.5 pl-2 xl:min-w-full'>
          <ReactApexChart options={options} series={series} type='bar' height={180} />
        </div>
      </div>
    </div>
  );
}
