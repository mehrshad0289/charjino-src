import Image from 'next/image';

type AvatarSize = 'xsmall' | 'small' | 'medium' | 'large' | 'xlarge' | 'xxlarge';
type AvatarStatus = 'online' | 'offline' | 'busy' | 'none';

const AVATAR_SIZE: Record<AvatarSize, string> = {
  xsmall: 'h-6 w-6 max-w-6',
  small: 'h-8 w-8 max-w-8',
  medium: 'h-10 w-10 max-w-10',
  large: 'h-12 w-12 max-w-12',
  xlarge: 'h-14 w-14 max-w-14',
  xxlarge: 'h-16 w-16 max-w-16'
};

const AVATAR_STATUS_SIZE: Record<AvatarSize, string> = {
  xsmall: 'h-1.5 w-1.5 max-w-1.5',
  small: 'h-2 w-2 max-w-2',
  medium: 'h-2.5 w-2.5 max-w-2.5',
  large: 'h-3 w-3 max-w-3',
  xlarge: 'h-3.5 w-3.5 max-w-3.5',
  xxlarge: 'h-4 w-4 max-w-4'
};

const AVATAR_STATUS_COLOR = {
  online: 'bg-success-500',
  offline: 'bg-error-400',
  busy: 'bg-warning-500'
} as const;

type AvatarProps = {
  src: string;
  alt?: string;
  size?: AvatarSize;
  status?: AvatarStatus;
  className?: string;
};

export function Avatar({
  src,
  alt = 'آواتار کاربر',
  size = 'medium',
  status = 'none',
  className = ''
}: AvatarProps) {
  return (
    <div className={`relative rounded-full ${AVATAR_SIZE[size]} ${className}`}>
      <Image
        width={0}
        height={0}
        sizes='100vw'
        src={src}
        alt={alt}
        className='w-full rounded-full object-cover'
      />
      {status !== 'none' ? (
        <span
          className={`absolute right-0 bottom-0 rounded-full border-[1.5px] border-white dark:border-gray-900 ${AVATAR_STATUS_SIZE[size]} ${AVATAR_STATUS_COLOR[status]}`}
        />
      ) : null}
    </div>
  );
}
