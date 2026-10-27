import type { ElementType, ReactNode } from 'react';

type ContainerProps = {
  children: ReactNode;
  className?: string;
  as?: ElementType;
};

export function Container({
  children,
  className = '',
  as: Tag = 'div',
}: ContainerProps) {
  return (
    <Tag className={`container mx-auto w-full px-4 xl:max-w-7xl ${className}`.trim()}>
      {children}
    </Tag>
  );
}
