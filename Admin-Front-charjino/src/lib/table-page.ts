export const TABLE_PAGE_SIZES = [10, 30, 50, 60] as const;

export type TablePageSize = (typeof TABLE_PAGE_SIZES)[number];

export function parseTablePerPage(raw?: string | number | null, fallback: TablePageSize = 10): TablePageSize {
  const value = typeof raw === 'number' ? raw : Number(raw);
  return (TABLE_PAGE_SIZES as readonly number[]).includes(value) ? (value as TablePageSize) : fallback;
}

export function parseTablePage(raw?: string | number | null): number {
  const value = typeof raw === 'number' ? raw : Number(raw);
  return Number.isFinite(value) && value >= 1 ? Math.floor(value) : 1;
}
