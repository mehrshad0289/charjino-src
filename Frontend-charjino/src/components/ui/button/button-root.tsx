import type { ReactNode } from 'react';
import Link from 'next/link';
import { ButtonGlyphIcon, ButtonLoaderIcon } from '../icon';
import { buttonClassName } from './styles';

export type ButtonColor = 'primary' | 'secondary' | 'error' | 'success';
export type ButtonStyle = 'fill' | 'outline' | 'light' | 'text';
export type ButtonSize = 'L' | 'M';
export type ButtonPreview = 'default' | 'hover' | 'loading' | 'disabled';

type ButtonProps = {
  children?: ReactNode;
  color?: ButtonColor;
  variant?: ButtonStyle;
  size?: ButtonSize;
  loading?: boolean;
  disabled?: boolean;
  icon?: ReactNode;
  iconPosition?: 'start' | 'end';
  leftIcon?: boolean;
  rightIcon?: boolean;
  previewState?: ButtonPreview;
  fullWidth?: boolean;
  className?: string;
  href?: string;
  type?: 'button' | 'submit';
  onClick?: () => void;
};

export function Button({
  children = 'دکمه',
  color = 'primary',
  variant = 'fill',
  size = 'L',
  loading = false,
  disabled = false,
  icon,
  iconPosition = 'start',
  leftIcon = false,
  rightIcon = false,
  previewState = 'default',
  fullWidth = false,
  className: extraClass = '',
  href,
  type = 'button',
  onClick,
}: ButtonProps) {
  const isLoading = loading || previewState === 'loading';
  const isDisabled = disabled || previewState === 'disabled';
  const className = buttonClassName({
    color,
    variant,
    size,
    previewState,
    disabled: isDisabled,
    loading: isLoading,
    fullWidth,
    className: extraClass,
  });

  const iconSize = size === 'M' ? 16 : 20;
  const glyph = isLoading ? <ButtonLoaderIcon size={iconSize} /> : <ButtonGlyphIcon size={iconSize} />;
  const customIcon = isLoading ? null : icon;
  const startIcon =
    isLoading
      ? glyph
      : iconPosition === 'end'
        ? leftIcon && !customIcon
          ? glyph
          : null
        : customIcon ?? (leftIcon ? glyph : null);
  const endIcon =
    isLoading
      ? null
      : iconPosition === 'end' && customIcon
        ? customIcon
        : !customIcon && rightIcon
          ? glyph
          : null;

  const content = (
    <>
      {startIcon}
      <span>{children}</span>
      {endIcon}
    </>
  );

  if (href) {
    return (
      <Link href={href} className={className} aria-busy={isLoading || undefined} aria-disabled={isDisabled || isLoading || undefined}>
        {content}
      </Link>
    );
  }

  return (
    <button
      type={type}
      className={className}
      disabled={isDisabled || isLoading}
      aria-busy={isLoading || undefined}
      onClick={onClick}
    >
      {content}
    </button>
  );
}
