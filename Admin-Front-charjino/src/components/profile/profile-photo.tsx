import Image from 'next/image';

type ProfilePhotoProps = {
  src: string;
  alt: string;
  width: number;
  height: number;
  className?: string;
};

export function ProfilePhoto({ src, alt, width, height, className }: ProfilePhotoProps) {
  if (src.startsWith('blob:')) {
    return <img src={src} alt={alt} width={width} height={height} className={className} />;
  }

  return <Image src={src} alt={alt} width={width} height={height} className={className} />;
}
