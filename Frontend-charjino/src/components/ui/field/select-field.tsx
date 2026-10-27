'use client';

import {
  useEffect,
  useId,
  useLayoutEffect,
  useMemo,
  useRef,
  useState,
  type CSSProperties,
  type ReactNode,
} from 'react';
import { ChevronIcon, SearchNormalIcon, TickCircleIcon, UserIcon } from '../icon';
import {
  INPUT_INNER_CLASS,
  MENU_ITEM_ACTIVE_CLASS,
  MENU_ITEM_CLASS,
  MENU_LIST_CLASS,
  MENU_SEARCH_CLASS,
  MENU_SURFACE_CLASS,
  WRAP_CLASS,
  inputClass,
  shopControlClass,
} from './classNames';
import { hotjarSuppress } from '@/lib/hotjar';
import { Field } from './field-shell';
import type { FieldProps } from './types';
import { useOutsideDismiss } from './use-outside-dismiss';

const ITEMS = ['Sample item 1', 'Sample item 2', 'Sample item 3'];

export type SelectOption = {
  value: string;
  label: string;
  icon?: ReactNode;
};

type SelectFieldProps = FieldProps & {
  placeholder?: string;
  open?: boolean;
  items?: readonly string[];
  options?: readonly SelectOption[];
  showItemIcon?: boolean;
  searchable?: boolean;
  searchPlaceholder?: string;
  emptyText?: string;
  icon?: ReactNode;
  variant?: 'default' | 'shop';
  placement?: 'fixed' | 'local';
  className?: string;
};

function optionMatches(option: SelectOption, query: string) {
  const needle = query.trim().toLowerCase();
  if (!needle) return true;
  return (
    option.label.toLowerCase().includes(needle) || option.value.toLowerCase().includes(needle)
  );
}

function menuBox(node: HTMLElement, placement: 'fixed' | 'local'): CSSProperties {
  const rect = node.getBoundingClientRect();
  const gap = 8;
  const spaceBelow = window.innerHeight - rect.bottom - gap;
  const spaceAbove = rect.top - gap;
  const maxH = 288;
  const openUp = spaceBelow < 160 && spaceAbove > spaceBelow;
  const height = Math.min(maxH, openUp ? spaceAbove : spaceBelow);
  if (placement === 'local') {
    return {
      position: 'absolute',
      left: 0,
      right: 0,
      width: 'auto',
      zIndex: 80,
      maxHeight: Math.max(120, height),
      ...(openUp
        ? { top: 'auto', bottom: 'calc(100% + 8px)' }
        : { top: 'calc(100% + 8px)', bottom: 'auto' }),
    };
  }
  return {
    position: 'fixed',
    left: rect.left,
    width: rect.width,
    right: 'auto',
    zIndex: 80,
    maxHeight: Math.max(120, height),
    ...(openUp
      ? { top: 'auto', bottom: window.innerHeight - rect.top + gap }
      : { top: rect.bottom + gap, bottom: 'auto' }),
  };
}

export function SelectField({
  label,
  hint,
  placeholder = 'انتخاب کنید',
  open = false,
  previewState = 'default',
  items,
  options,
  value,
  compact = false,
  searchable,
  searchPlaceholder = 'جستجو...',
  emptyText = 'موردی پیدا نشد.',
  icon,
  variant = 'default',
  placement = 'fixed',
  className,
  onChange,
  onBlur,
  showItemIcon = true,
}: SelectFieldProps) {
  const resolved: SelectOption[] =
    options?.slice() ??
    (items ?? ITEMS).map((item) => ({ value: item, label: item }));
  const listId = useId();
  const [isOpen, setIsOpen] = useState(open);
  const [inner, setInner] = useState(open ? resolved[0]?.value ?? '' : '');
  const [query, setQuery] = useState('');
  const [menuStyle, setMenuStyle] = useState<CSSProperties>();
  const rootRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const searchRef = useRef<HTMLInputElement>(null);
  const selected = value === undefined ? inner : value;
  const selectedOption = resolved.find((item) => item.value === selected);
  const selectedLabel = selectedOption?.label ?? selected;
  const triggerIcon = selectedOption?.icon ?? icon;
  const isDisabled = previewState === 'disabled';
  const canSearch = searchable ?? resolved.length > 6;
  const filtered = useMemo(
    () => (canSearch ? resolved.filter((item) => optionMatches(item, query)) : resolved),
    [canSearch, query, resolved],
  );
  const fallbackItemIcon = icon ?? (showItemIcon ? <UserIcon size={18} /> : null);
  useOutsideDismiss(isOpen && !isDisabled, rootRef, () => setIsOpen(false));

  useLayoutEffect(() => {
    if (!isOpen) {
      setMenuStyle(undefined);
      return;
    }
    const node = triggerRef.current;
    if (!node) return;
    const update = () => setMenuStyle(menuBox(node, placement));
    update();
    window.addEventListener('resize', update);
    window.addEventListener('scroll', update, true);
    return () => {
      window.removeEventListener('resize', update);
      window.removeEventListener('scroll', update, true);
    };
  }, [isOpen, placement]);

  useEffect(() => {
    if (!isOpen) {
      setQuery('');
      return;
    }
    const fine = window.matchMedia('(pointer: fine)').matches;
    if (!canSearch || !fine) return;
    const handle = window.setTimeout(() => {
      searchRef.current?.focus({ preventScroll: true });
    }, 0);
    return () => window.clearTimeout(handle);
  }, [isOpen, canSearch]);

  function choose(next: string) {
    if (value === undefined) setInner(next);
    onChange?.(next);
    setIsOpen(false);
    onBlur?.();
  }

  function toggle() {
    if (isDisabled) return;
    if (isOpen) {
      setIsOpen(false);
      return;
    }
    if (triggerRef.current) setMenuStyle(menuBox(triggerRef.current, placement));
    setIsOpen(true);
  }

  const triggerClass =
    variant === 'shop'
      ? shopControlClass(Boolean(selectedLabel), [className ?? ''])
      : inputClass(previewState, Boolean(selected), ['cursor-pointer', className ?? '']);

  return (
    <Field
      label={label}
      hint={hint}
      previewState={previewState}
      compact={compact || variant === 'shop'}
      className={isOpen ? 'z-50 overflow-visible' : 'overflow-visible'}
    >
      <div
        ref={rootRef}
        className={`${WRAP_CLASS} overflow-visible ${isOpen ? 'z-50' : ''}`}
        onKeyDown={(event) => {
          if (event.key !== 'Escape' || !isOpen) return;
          event.stopPropagation();
          setIsOpen(false);
        }}
      >
        <button
          type="button"
          ref={triggerRef}
          className={triggerClass}
          disabled={isDisabled}
          aria-haspopup="listbox"
          aria-expanded={isOpen}
          aria-controls={listId}
          onClick={toggle}
        >
          {triggerIcon ? <span className="inline-flex size-6 shrink-0 items-center justify-center text-text/70">{triggerIcon}</span> : null}
          <span className={`${INPUT_INNER_CLASS} min-w-0 truncate ${triggerIcon ? 'ms-2' : ''}`}>
            {selectedLabel || placeholder}
          </span>
          <ChevronIcon up={isOpen} />
        </button>
        {isOpen && !isDisabled && menuStyle ? (
          <div
            className={`${MENU_SURFACE_CLASS} flex flex-col overflow-hidden`}
            role="presentation"
            style={menuStyle}
          >
            {canSearch ? (
              <label className={MENU_SEARCH_CLASS}>
                <SearchNormalIcon size={18} />
                <input
                  {...hotjarSuppress}
                  ref={searchRef}
                  className={`${INPUT_INNER_CLASS} min-w-0 flex-1`}
                  placeholder={searchPlaceholder}
                  value={query}
                  aria-label={searchPlaceholder}
                  onChange={(event) => setQuery(event.target.value)}
                />
              </label>
            ) : null}
            <div id={listId} role="listbox" aria-label={label} className={MENU_LIST_CLASS}>
              {filtered.map((item) => {
                const active = item.value === selected;
                const rowIcon = item.icon ?? fallbackItemIcon;
                return (
                  <button
                    key={item.value}
                    type="button"
                    role="option"
                    aria-selected={active}
                    className={`${MENU_ITEM_CLASS} ${active ? MENU_ITEM_ACTIVE_CLASS : ''}`}
                    onClick={() => choose(item.value)}
                  >
                    {rowIcon ? <span className="inline-flex size-6 shrink-0 items-center justify-center text-text/70">{rowIcon}</span> : null}
                    <span className="min-w-0 flex-1 truncate">{item.label}</span>
                    {active ? (
                      <span className="inline-flex shrink-0 text-primary-600">
                        <TickCircleIcon size={16} />
                      </span>
                    ) : null}
                  </button>
                );
              })}
              {filtered.length === 0 ? (
                <p className="px-3 py-4 text-center text-sm text-text/55">{emptyText}</p>
              ) : null}
            </div>
          </div>
        ) : null}
      </div>
    </Field>
  );
}
