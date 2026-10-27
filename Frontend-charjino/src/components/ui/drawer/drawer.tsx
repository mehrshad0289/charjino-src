'use client';

import { useEffect, useId, useLayoutEffect, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { CloseIcon } from '../icon';
import { isNativeFilePickerOpen } from '../file-picker';
import { lockPageScroll } from './lock-page-scroll';
import {
  DRAWER_BODY,
  DRAWER_CLOSE,
  DRAWER_FOOTER,
  DRAWER_HEADER,
  DRAWER_OVERLAY,
  DRAWER_PANEL,
  DRAWER_TITLE,
} from './styles';

const EXIT_MS = 300;

export type DrawerProps = {
  open: boolean;
  onClose: () => void;
  title: string;
  children: ReactNode;
  toolbar?: ReactNode;
  footer?: ReactNode;
};

export function Drawer({
  open,
  onClose,
  title,
  children,
  toolbar,
  footer,
}: DrawerProps) {
  const titleId = useId();
  const [ready, setReady] = useState(false);
  const [rendered, setRendered] = useState(false);
  const [entered, setEntered] = useState(false);

  useEffect(() => {
    setReady(true);
  }, []);

  useEffect(() => {
    if (open) {
      setRendered(true);
      const frame = window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => setEntered(true));
      });
      return () => window.cancelAnimationFrame(frame);
    }

    setEntered(false);
    const timer = window.setTimeout(() => setRendered(false), EXIT_MS);
    return () => window.clearTimeout(timer);
  }, [open]);

  const shouldLock = open || rendered;

  useLayoutEffect(() => {
    if (!shouldLock) return;
    return lockPageScroll();
  }, [shouldLock]);

  useEffect(() => {
    if (!shouldLock) return;

    function onKeyDown(event: KeyboardEvent) {
      if (event.key !== 'Escape') return;
      if (isNativeFilePickerOpen()) return;
      onClose();
    }

    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [shouldLock, onClose]);

  if (!ready || !rendered) return null;

  return createPortal(
    <>
      <button
        type="button"
        aria-label="بستن"
        className={`${DRAWER_OVERLAY} ${entered ? 'opacity-100' : 'opacity-0'}`}
        onClick={() => {
          if (isNativeFilePickerOpen()) return;
          onClose();
        }}
      />
      <aside
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        className={`${DRAWER_PANEL} ${entered ? 'translate-x-0' : 'translate-x-full'}`}
      >
        <header className={DRAWER_HEADER}>
          <h2 id={titleId} className={DRAWER_TITLE}>
            {title}
          </h2>
          <button type="button" aria-label="بستن" className={DRAWER_CLOSE} onClick={onClose}>
            <CloseIcon size={24} />
          </button>
        </header>
        {toolbar}
        <div className={DRAWER_BODY}>{children}</div>
        {footer ? <footer className={DRAWER_FOOTER}>{footer}</footer> : null}
      </aside>
    </>,
    document.body,
  );
}
