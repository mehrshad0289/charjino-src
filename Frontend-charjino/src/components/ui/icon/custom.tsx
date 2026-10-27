import type { IconProps } from './types';

export function CloseIcon({ size = 24 }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" aria-hidden>
      <path
        d="M7 7l10 10M17 7L7 17"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
      />
    </svg>
  );
}

export function ChevronIcon({ size = 16, up = false }: IconProps & { up?: boolean }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 16 16"
      fill="none"
      aria-hidden
      style={{ transform: up ? 'rotate(180deg)' : undefined }}
    >
      <path
        d="M4 6l4 4 4-4"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

export function UserIcon({ size = 20 }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" aria-hidden>
      <circle cx="12" cy="8" r="3" stroke="currentColor" strokeWidth="1.5" />
      <path
        d="M5 19c1.2-3 3.6-4.5 7-4.5S17.8 16 19 19"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
      />
    </svg>
  );
}

export function ButtonLoaderIcon({ size = 25 }: IconProps) {
  return (
    <svg
      className="block animate-spin"
      width={size}
      height={size}
      viewBox="0 0 25 25"
      fill="none"
      aria-hidden
    >
      <path
        d="M12.1667 0.5V5.16667M12.1667 19.1667V23.8333M3.91833 3.91833L7.22 7.22M17.1133 17.1133L20.415 20.415M0.5 12.1667H5.16667M19.1667 12.1667H23.8333M3.91833 20.415L7.22 17.1133M17.1133 7.22L20.415 3.91833"
        stroke="currentColor"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}
