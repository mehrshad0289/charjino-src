const GRID_CLASS =
  'grid grid-cols-2 items-stretch gap-4 sm:grid-cols-4 sm:gap-x-6 sm:gap-y-8 lg:grid-cols-6';

export function catalogBrowseGridClass() {
  return GRID_CLASS;
}

export function CatalogBrowseSkeleton({ count = 12 }: { count?: number }) {
  return (
    <div className={GRID_CLASS} aria-hidden>
      {Array.from({ length: count }, (_, index) => (
        <div
          key={index}
          className="flex h-full flex-col overflow-hidden rounded-2xl bg-card shadow-card ring-1 ring-border"
        >
          <div className="aspect-square w-full shrink-0 animate-pulse bg-card-muted" />
          <div className="flex min-h-[4.25rem] shrink-0 flex-col justify-center gap-2 px-3 py-2 sm:px-4">
            <div className="h-3 w-4/5 animate-pulse rounded bg-card-muted" />
            <div className="h-3 w-1/3 animate-pulse rounded bg-card-muted" />
          </div>
        </div>
      ))}
    </div>
  );
}
