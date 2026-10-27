'use client';

import { useId, useState } from 'react';
import {
  SWITCH_COPY,
  SWITCH_ROW,
  SWITCH_TEXT,
  SWITCH_THUMB,
  SWITCH_THUMB_ON,
  SWITCH_TITLE,
  SWITCH_TRACK,
  SWITCH_TRACK_ALIGN,
  type SwitchSize,
} from './styles';

export type { SwitchSize };

export type SwitchProps = {
  size?: SwitchSize;
  checked?: boolean;
  defaultChecked?: boolean;
  onChange?: (checked: boolean) => void;
  disabled?: boolean;
  title?: string;
  description?: string;
  className?: string;
  titleClassName?: string;
  'aria-label'?: string;
};

const FOCUS =
  'cursor-pointer focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-100 disabled:cursor-not-allowed disabled:opacity-50';

function SwitchTrack({
  size,
  on,
  align,
}: {
  size: SwitchSize;
  on: boolean;
  align?: boolean;
}) {
  return (
    <span
      aria-hidden
      dir="ltr"
      className={[
        'relative inline-flex shrink-0 rounded-full transition-colors duration-150 ease-standard',
        SWITCH_TRACK[size],
        on ? 'bg-switch-on' : 'bg-switch-off',
        align ? SWITCH_TRACK_ALIGN[size] : '',
      ]
        .filter(Boolean)
        .join(' ')}
    >
      <span
        className={[
          'absolute top-0.5 left-0.5 rounded-full bg-white shadow-switch transition-transform duration-150 ease-standard',
          SWITCH_THUMB[size],
          on ? SWITCH_THUMB_ON[size] : 'translate-x-0',
        ].join(' ')}
      />
    </span>
  );
}

export function Switch({
  size = 'S',
  checked,
  defaultChecked = false,
  onChange,
  disabled = false,
  title,
  description,
  className = '',
  titleClassName = '',
  'aria-label': ariaLabel,
}: SwitchProps) {
  const titleId = useId();
  const [internal, setInternal] = useState(defaultChecked);
  const isControlled = checked !== undefined;
  const on = isControlled ? checked : internal;
  const labeled = Boolean(title || description);

  function toggle() {
    if (disabled) return;
    const next = !on;
    if (!isControlled) setInternal(next);
    onChange?.(next);
  }

  if (!labeled) {
    return (
      <button
        type="button"
        role="switch"
        dir="ltr"
        aria-checked={on}
        aria-label={ariaLabel ?? 'سوییچ'}
        disabled={disabled}
        onClick={toggle}
        className={['border-0 bg-transparent p-0', FOCUS, className]
          .filter(Boolean)
          .join(' ')}
      >
        <SwitchTrack size={size} on={on} />
      </button>
    );
  }

  return (
    <button
      type="button"
      role="switch"
      aria-checked={on}
      aria-labelledby={title ? titleId : undefined}
      aria-label={title ? undefined : (ariaLabel ?? 'سوییچ')}
      disabled={disabled}
      onClick={toggle}
      className={[
        'border-0 bg-transparent p-0 text-start',
        SWITCH_ROW,
        FOCUS,
        className,
      ]
        .filter(Boolean)
        .join(' ')}
    >
      <span className={SWITCH_TEXT}>
        {title ? (
          <span id={titleId} className={titleClassName || SWITCH_TITLE}>
            {title}
          </span>
        ) : null}
        {description ? <span className={SWITCH_COPY}>{description}</span> : null}
      </span>
      <SwitchTrack size={size} on={on} align={Boolean(title)} />
    </button>
  );
}
