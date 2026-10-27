import type { ReactNode } from 'react';

type TableProps = {
  children: ReactNode;
  className?: string;
};

export function Table({ children, className }: TableProps) {
  return <table className={`min-w-full  ${className ?? ''}`}>{children}</table>;
}

export function TableHeader({ children, className }: TableProps) {
  return <thead className={className}>{children}</thead>;
}

export function TableBody({ children, className }: TableProps) {
  return <tbody className={className}>{children}</tbody>;
}

export function TableRow({ children, className }: TableProps) {
  return <tr className={className}>{children}</tr>;
}

type TableCellProps = {
  children: ReactNode;
  isHeader?: boolean;
  header?: boolean;
  colSpan?: number;
  className?: string;
};

export function TableCell({ children, isHeader = false, header = false, colSpan, className }: TableCellProps) {
  const CellTag = isHeader || header ? 'th' : 'td';
  return (
    <CellTag colSpan={colSpan} className={` ${className ?? ''}`}>
      {children}
    </CellTag>
  );
}
