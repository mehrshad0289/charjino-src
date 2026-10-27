'use client';

import {
  useId,
  useLayoutEffect,
  useSyncExternalStore,
  type ChangeEvent,
  type ReactNode,
} from 'react';
import { createPortal } from 'react-dom';
import { hotjarSuppress } from '@/lib/hotjar';

type FilePickerProps = {
  accept?: string;
  multiple?: boolean;
  disabled?: boolean;
  onSelect?: (file: File) => void;
  onSelectMany?: (files: File[]) => void;
  children: (open: () => void) => ReactNode;
};

const PICKER_GUARD_MS = 1500;

let nativePickerOpen = false;
let releaseTimer = 0;

function markNativePickerOpen() {
  window.clearTimeout(releaseTimer);
  nativePickerOpen = true;
}

function releaseNativePickerSoon() {
  window.clearTimeout(releaseTimer);
  releaseTimer = window.setTimeout(() => {
    nativePickerOpen = false;
  }, PICKER_GUARD_MS);
}

export function isNativeFilePickerOpen() {
  return nativePickerOpen;
}

function subscribe() {
  return () => undefined;
}

export function FilePicker({
  accept,
  multiple = false,
  disabled,
  onSelect,
  onSelectMany,
  children,
}: FilePickerProps) {
  const inputId = useId();
  const mounted = useSyncExternalStore(subscribe, () => true, () => false);

  useLayoutEffect(() => {
    function onWindowFocus() {
      if (!nativePickerOpen) return;
      releaseNativePickerSoon();
    }

    window.addEventListener('focus', onWindowFocus);
    return () => window.removeEventListener('focus', onWindowFocus);
  }, []);

  useLayoutEffect(() => {
    const input = document.getElementById(inputId);
    if (!(input instanceof HTMLInputElement)) return;
    input.addEventListener('cancel', releaseNativePickerSoon);
    return () => input.removeEventListener('cancel', releaseNativePickerSoon);
  }, [inputId, mounted]);

  function open() {
    if (disabled) return;
    const input = document.getElementById(inputId);
    if (!(input instanceof HTMLInputElement)) return;
    markNativePickerOpen();
    input.click();
  }

  function handleChange(event: ChangeEvent<HTMLInputElement>) {
    const files = Array.from(event.target.files ?? []);
    event.target.value = '';
    markNativePickerOpen();
    releaseNativePickerSoon();
    if (files.length === 0) return;
    if (onSelectMany) onSelectMany(files);
    else if (files[0]) onSelect?.(files[0]);
  }

  const input = (
    <input
      {...hotjarSuppress}
      id={inputId}
      type="file"
      accept={accept}
      multiple={multiple}
      tabIndex={-1}
      aria-hidden="true"
      className="pointer-events-none fixed left-0 top-0 h-px w-px opacity-0"
      onChange={handleChange}
    />
  );

  return (
    <>
      {mounted ? createPortal(input, document.body) : null}
      {children(open)}
    </>
  );
}
