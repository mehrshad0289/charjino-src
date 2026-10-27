'use client';

import type { MouseEvent, ReactNode } from 'react';
import Link from 'next/link';

type ChipAppearance = 'category' | 'filter' | 'icon';

type ChipProps = {
  label?: string;
  selected?: boolean;
  disabled?: boolean;
  href?: string;
  onClick?: (event: MouseEvent<HTMLButtonElement | HTMLAnchorElement>) => void;
  icon?: ReactNode;
  endIcon?: ReactNode;
  appearance?: ChipAppearance;
  className?: string;
  'aria-label'?: string;
  'aria-expanded'?: boolean;
  'aria-haspopup'?: 'listbox' | 'menu';
  'aria-controls'?: string;
  tabIndex?: number;
  decorative?: boolean;
};

export function Chip({
  label,
  selected = false,
  disabled = false,
  href,
  onClick,
  icon,
  endIcon,
  appearance = 'category',
  className = '',
  'aria-label': ariaLabel,
  'aria-expanded': ariaExpanded,
  'aria-haspopup': ariaHasPopup,
  'aria-controls': ariaControls,
  tabIndex,
  decorative = false,
}: ChipProps) {
  const filterClass = [
    'inline-flex h-[30px] min-h-[30px] cursor-pointer items-center justify-center gap-1 rounded-lg border border-border/60 bg-transparent  px-3 py-1 font-sans text-xs font-normal leading-none text-text',
    selected ? 'bg-text/8' : 'hover:bg-text/5',
  ].join(' ');

  const categoryClass = [
    'flex flex-col cursor-pointer items-center gap-2 rounded-sm border-0 p-4 font-sans text-sm font-semibold',
    selected
      ? 'bg-primary-600 text-white hover:bg-primary-600/75'
      : 'bg-card text-text shadow-card  hover:bg-text/5',
  ].join(' ');

  const iconClass =
    'inline-flex h-7 w-11 cursor-pointer items-center justify-center rounded-lg border-0 bg-transparent p-0 font-sans text-text hover:bg-text/8';

  const appearanceClass =
    appearance === 'filter'
      ? filterClass
      : appearance === 'icon'
        ? iconClass
        : categoryClass;

  const classes = [
    'transition-all duration-300 ease-out focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 ',
    appearanceClass,
    className,
  ].join(' ');

  const content = (
    <>
      {icon}
      {label ? <span>{label}</span> : null}
      {endIcon}
    </>
  );

  if (href && !disabled) {
    return (
      <Link
        href={href}
        className={classes}
        aria-label={ariaLabel ?? label}
        tabIndex={decorative ? -1 : tabIndex}
        aria-hidden={decorative || undefined}
        onClick={onClick}
      >
        {content}
      </Link>
    );
  }

  if (decorative) {
    return <span className={classes}>{content}</span>;
  }

  return (
    <button
      type="button"
      aria-label={ariaLabel ?? label}
      aria-expanded={ariaExpanded}
      aria-haspopup={ariaHasPopup}
      aria-controls={ariaControls}
      className={classes}
      tabIndex={tabIndex}
      disabled={disabled}
      onClick={onClick}
    >
      {content}
    </button>
  );
}
