import type { ReactNode } from 'react';

type FaqIconProps = {
  size?: number;
  className?: string;
};

function FaqSvg({
  size = 18,
  className = '',
  strokeWidth = 2,
  children
}: FaqIconProps & { strokeWidth?: number; children: ReactNode }) {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={strokeWidth}
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden
    >
      {children}
    </svg>
  );
}

export function FaqGamepadIcon(props: FaqIconProps) {
  return (
    <FaqSvg size={26} strokeWidth={1.8} {...props}>
      <rect x="2" y="6" width="20" height="12" rx="3" />
      <circle cx="8" cy="12" r="1.2" fill="currentColor" stroke="none" />
      <circle cx="16" cy="10.5" r=".9" fill="currentColor" stroke="none" />
      <circle cx="18" cy="12.5" r=".9" fill="currentColor" stroke="none" />
      <path d="M7 10v4M5 12h4" />
    </FaqSvg>
  );
}

export function FaqLockIcon(props: FaqIconProps) {
  return (
    <FaqSvg {...props}>
      <rect x="5" y="11" width="14" height="10" rx="2" />
      <path d="M8 11V8a4 4 0 0 1 8 0v3" />
      <circle cx="12" cy="16" r="1.2" fill="currentColor" stroke="none" />
    </FaqSvg>
  );
}

export function FaqBoltIcon(props: FaqIconProps) {
  return (
    <FaqSvg {...props}>
      <path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z" />
    </FaqSvg>
  );
}

export function FaqGridIcon(props: FaqIconProps) {
  return (
    <FaqSvg {...props}>
      <rect x="3" y="4" width="7" height="7" rx="1.5" />
      <rect x="14" y="4" width="7" height="7" rx="1.5" />
      <rect x="3" y="13" width="7" height="7" rx="1.5" />
      <rect x="14" y="13" width="7" height="7" rx="1.5" />
    </FaqSvg>
  );
}

export function FaqGiftIcon(props: FaqIconProps) {
  return (
    <FaqSvg {...props}>
      <rect x="2" y="6" width="20" height="13" rx="2" />
      <path d="M2 10h20" />
      <circle cx="12" cy="14.5" r="1.5" />
    </FaqSvg>
  );
}

export function FaqSearchIcon(props: FaqIconProps) {
  return (
    <FaqSvg {...props}>
      <circle cx="11" cy="11" r="7" />
      <path d="M21 21l-4.2-4.2" />
      <path d="M11 8v3l2 1.5" />
    </FaqSvg>
  );
}

export function FaqAlertIcon(props: FaqIconProps) {
  return (
    <FaqSvg {...props}>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 8v5" />
      <circle cx="12" cy="16.5" r="1" fill="currentColor" stroke="none" />
    </FaqSvg>
  );
}

export function FaqChevronIcon(props: FaqIconProps) {
  return (
    <FaqSvg size={14} strokeWidth={2.4} {...props}>
      <path d="M6 9l6 6 6-6" />
    </FaqSvg>
  );
}
