import { platformIconSrc } from '@/lib/platform-icon';

export function PlatformIcon({
  platform,
  size = 22,
}: {
  platform?: string | null;
  size?: number;
}) {
  return (
    <img
      src={platformIconSrc(platform)}
      width={size}
      height={size}
      alt=""
      aria-hidden
      className="shrink-0 object-contain"
      style={{ width: size, height: size }}
    />
  );
}
