import type { IconProps } from './types';

type FileIconProps = IconProps & {
  src: string;
};

function FileIcon({ src, size = 20 }: FileIconProps) {
  return (
    <img
      src={src}
      width={size}
      height={size}
      alt=""
      aria-hidden
      className="shrink-0"
    />
  );
}

export function AdvertisingIcon({ size = 20 }: IconProps) {
  return <FileIcon src="/image/icon/advertising.svg" size={size} />;
}

export function TenderIcon({ size = 20 }: IconProps) {
  return <FileIcon src="/image/icon/tender.svg" size={size} />;
}

export function PelleIcon({ size = 20 }: IconProps) {
  return <FileIcon src="/image/icon/pelle.svg" size={size} />;
}
