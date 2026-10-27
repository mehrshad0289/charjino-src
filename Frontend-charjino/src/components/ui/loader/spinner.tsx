export function Spinner({ size = 48, className = '' }: { size?: number; className?: string }) {
  return (
    <span
      className={`relative inline-flex items-center justify-center ${className}`.trim()}
      style={{ width: size, height: size }}
      aria-hidden
    >
      <span className="absolute inset-0 animate-spin rounded-full border-[3px] border-primary-600/20 border-t-primary-600" />
      <span className="absolute inset-[22%] animate-pulse rounded-md bg-primary-600/90" />
    </span>
  );
}
