'use client';

import { useEffect, useId, useLayoutEffect, useState, useSyncExternalStore, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { CloseIcon } from '../icon';
import { isNativeFilePickerOpen } from '../file-picker';
import { lockPageScroll } from '../drawer/lock-page-scroll';
import {
  MODAL_BODY,
  MODAL_CLOSE,
  MODAL_FOOTER,
  MODAL_HEADER,
  MODAL_OVERLAY,
  MODAL_PANEL,
  MODAL_SHELL,
  MODAL_TITLE,
} from './styles';

const EXIT_MS = 300;

function subscribe() {
  return () => undefined;
}

export type ModalProps = {
  open: boolean;
  onClose: () => void;
  title: string;
  description?: ReactNode;
  children: ReactNode;
  toolbar?: ReactNode;
  footer?: ReactNode;
  panelClassName?: string;
  shellClassName?: string;
  headerClassName?: string;
  titleClassName?: string;
  bodyClassName?: string;
  footerClassName?: string;
  closeClassName?: string;
  closeIcon?: ReactNode;
  close?: ReactNode;
};

export function Modal({
  open,
  onClose,
  title,
  description,
  children,
  toolbar,
  footer,
  panelClassName,
  shellClassName,
  headerClassName,
  titleClassName,
  bodyClassName,
  footerClassName,
  closeClassName,
  closeIcon,
  close,
}: ModalProps) {
  const titleId = useId();
  const ready = useSyncExternalStore(subscribe, () => true, () => false);
  const [rendered, setRendered] = useState(false);
  const [entered, setEntered] = useState(false);

  useEffect(() => {
    if (open) {
      const enterFrame = window.requestAnimationFrame(() => {
        setRendered(true);
        window.requestAnimationFrame(() => setEntered(true));
      });
      return () => window.cancelAnimationFrame(enterFrame);
    }

    const exitFrame = window.requestAnimationFrame(() => setEntered(false));
    const timer = window.setTimeout(() => setRendered(false), EXIT_MS);
    return () => {
      window.cancelAnimationFrame(exitFrame);
      window.clearTimeout(timer);
    };
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
        className={`${MODAL_OVERLAY} ${entered ? 'opacity-100' : 'opacity-0'}`}
        onMouseDown={() => {
          if (isNativeFilePickerOpen()) return;
          onClose();
        }}
        onClick={() => {
          if (isNativeFilePickerOpen()) return;
          onClose();
        }}
      />
      <div className={shellClassName ?? MODAL_SHELL}>
        <div
          role="dialog"
          aria-modal="true"
          aria-labelledby={titleId}
          className={`${panelClassName ?? MODAL_PANEL} ${entered ? 'scale-100 opacity-100' : 'scale-95 opacity-0'}`}
        >
          <header
            className={
              headerClassName ??
              (description
                ? `${MODAL_HEADER} h-auto items-start`
                : MODAL_HEADER)
            }
          >
            <div className="flex min-w-0 flex-1 flex-col gap-3">
              <h2 id={titleId} className={titleClassName ?? MODAL_TITLE}>
                {title}
              </h2>
              {description ? (
                <div className="font-sans text-base font-normal leading-none text-gray-700">
                  {description}
                </div>
              ) : null}
            </div>
            {close ?? (
              <button
                type="button"
                aria-label="بستن"
                className={closeClassName ?? MODAL_CLOSE}
                onClick={onClose}
              >
                {closeIcon ?? <CloseIcon size={24} />}
              </button>
            )}
          </header>
          {toolbar ? <div className="shrink-0">{toolbar}</div> : null}
          <div className={bodyClassName ?? MODAL_BODY}>{children}</div>
          {footer ? <footer className={footerClassName ?? MODAL_FOOTER}>{footer}</footer> : null}
        </div>
      </div>
    </>,
    document.body,
  );
}
