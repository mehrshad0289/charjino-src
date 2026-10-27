import type { SVGProps } from 'react';

type CalendarChevronProps = SVGProps<SVGSVGElement>;

export function ChevronLeftIcon(props: CalendarChevronProps) {
  return (
    <svg
      xmlns='http://www.w3.org/2000/svg'
      width={24}
      height={24}
      viewBox='0 0 24 24'
      fill='none'
      strokeWidth={1.5}
      stroke='currentColor'
      strokeLinecap='round'
      strokeLinejoin='round'
      {...props}
    >
      <path d='M15 18l-6-6 6-6' />
    </svg>
  );
}

export function ChevronRightIcon(props: CalendarChevronProps) {
  return (
    <svg
      xmlns='http://www.w3.org/2000/svg'
      width={24}
      height={24}
      viewBox='0 0 24 24'
      fill='none'
      stroke='currentColor'
      strokeWidth={1.5}
      strokeLinecap='round'
      strokeLinejoin='round'
      {...props}
    >
      <path d='M9 18l6-6-6-6' />
    </svg>
  );
}
