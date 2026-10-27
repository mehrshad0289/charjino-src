import type { ReactNode } from 'react';

type ProfileInfoFieldProps = {
  label: string;
  children: ReactNode;
  className?: string;
};

export function ProfileInfoField({ label, children, className = 'w-full' }: ProfileInfoFieldProps) {
  return (
    <div className={className}>
      <p className='mb-2 text-xs leading-normal text-gray-500 dark:text-gray-400'>{label}</p>
      {typeof children === 'string' ? (
        <p className='text-sm font-medium text-gray-800 dark:text-white/90'>{children}</p>
      ) : (
        children
      )}
    </div>
  );
}
