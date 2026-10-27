'use client';

import { useMemo, useRef, useState } from 'react';
import { ChevronIcon, LocationIcon } from '../icon';
import { hotjarSuppress } from '@/lib/hotjar';
import { CITIES } from './cities';
import {
  INPUT_INNER_CLASS,
  MENU_CLASS,
  MENU_ITEM_ACTIVE_CLASS,
  MENU_ITEM_CLASS,
  MENU_LIST_CLASS,
  MENU_SEARCH_CLASS,
  WRAP_CLASS,
  inputClass,
} from './classNames';
import { Field } from './field-shell';
import type { FieldProps } from './types';
import { useOutsideDismiss } from './use-outside-dismiss';

type CityFieldProps = FieldProps & {
  placeholder?: string;
  items?: readonly string[];
  open?: boolean;
  className?: string;
};

export function CityField({
  label,
  hint,
  placeholder = 'شهر',
  items = CITIES,
  open = false,
  previewState = 'default',
  value,
  className,
  onChange,
}: CityFieldProps) {
  const [isOpen, setIsOpen] = useState(open);
  const [inner, setInner] = useState(open ? items[0] : '');
  const [query, setQuery] = useState('');
  const rootRef = useRef<HTMLDivElement>(null);
  const selected = value === undefined ? inner : value;
  const isDisabled = previewState === 'disabled';
  useOutsideDismiss(isOpen && !isDisabled, rootRef, () => setIsOpen(false));
  const filtered = useMemo(
    () => items.filter((item) => item.includes(query.trim())),
    [items, query],
  );

  function choose(item: string) {
    if (value === undefined) setInner(item);
    onChange?.(item);
    setQuery('');
    setIsOpen(false);
  }

  return (
    <Field label={label} hint={hint} previewState={previewState}>
      <div ref={rootRef} className={WRAP_CLASS}>
        <button
          type="button"
          className={inputClass(previewState, Boolean(selected), [className ?? ''])}
          disabled={isDisabled}
          onClick={() => setIsOpen((current) => !current)}
        >
          <LocationIcon size={24} />
          <span className={`${INPUT_INNER_CLASS} mr-2`}>{selected || placeholder}</span>
          <ChevronIcon up={isOpen} />
        </button>
        {isOpen && !isDisabled ? (
          <div className={`${MENU_CLASS} flex max-h-72 flex-col overflow-hidden`}>
            <label className={MENU_SEARCH_CLASS}>
              <input
                {...hotjarSuppress}
                className={`${INPUT_INNER_CLASS} min-w-0 flex-1`}
                placeholder="جستجوی شهر"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
              />
            </label>
            <div className={MENU_LIST_CLASS}>
              {filtered.map((item) => (
                <button
                  key={item}
                  type="button"
                  className={`${MENU_ITEM_CLASS} ${item === selected ? MENU_ITEM_ACTIVE_CLASS : ''}`}
                  onClick={() => choose(item)}
                >
                  <LocationIcon size={16} />
                  {item}
                </button>
              ))}
              {filtered.length === 0 ? (
                <p className="px-3 py-4 text-center text-sm text-text/55">شهری پیدا نشد.</p>
              ) : null}
            </div>
          </div>
        ) : null}
      </div>
    </Field>
  );
}
