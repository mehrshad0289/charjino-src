import type { ReactNode } from 'react';
import { Button } from '@/components/ui';
import { ArrowRightIcon2 } from '@/components/ui/icon';

type BackButtonProps = {
  onClick?: () => void;
  href?: string;
};

export function BackButton({ onClick, href }: BackButtonProps) {
  return (
    <Button
      size="M"
      variant="text"
      type="button"
      fullWidth={false}
      rightIcon={false}
      icon={<ArrowRightIcon2 size={20} />}
      className="text-text!"
      href={href}
      onClick={onClick}
    >
      بازگشت
    </Button>
  );
}
