import Link from 'next/link';
import type { MouseEvent, ReactNode } from 'react';

type DropdownItemProps = {
  tag?: 'a' | 'button';
  href?: string;
  onClick?: () => void;
  onItemClick?: () => void;
  baseClassName?: string;
  className?: string;
  children: ReactNode;
};

export function DropdownItem({
  tag = 'button',
  href,
  onClick,
  onItemClick,
  baseClassName = 'block w-full text-start px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900',
  className = '',
  children
}: DropdownItemProps) {
  const combinedClasses = `${baseClassName} ${className}`.trim();

  function handleClick(event: MouseEvent) {
    if (tag === 'button') event.preventDefault();
    onClick?.();
    onItemClick?.();
  }

  if (tag === 'a' && href) {
    return (
      <Link href={href} className={combinedClasses} onClick={handleClick}>
        {children}
      </Link>
    );
  }

  return (
    <button type='button' onClick={handleClick} className={combinedClasses}>
      {children}
    </button>
  );
}
