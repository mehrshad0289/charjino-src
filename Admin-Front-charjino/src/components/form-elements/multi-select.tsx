'use client';

import { useEffect, useRef, useState } from 'react';

type Option = {
  value: string;
  text: string;
};

type MultiSelectProps = {
  label: string;
  options: Option[];
  defaultSelected?: string[];
  value?: string[];
  onChange?: (selected: string[]) => void;
  disabled?: boolean;
  placeholder?: string;
};

export function MultiSelect({
  label,
  options,
  defaultSelected = [],
  value,
  onChange,
  disabled = false,
  placeholder = 'Select options'
}: MultiSelectProps) {
  const isControlled = value !== undefined;
  const [internalSelected, setInternalSelected] = useState<string[]>(defaultSelected);
  const selectedOptions = isControlled ? value : internalSelected;
  const [isOpen, setIsOpen] = useState(false);
  const [focusedIndex, setFocusedIndex] = useState(-1);
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [isOpen]);

  function updateSelection(next: string[]) {
    if (!isControlled) setInternalSelected(next);
    onChange?.(next);
  }

  function toggleDropdown() {
    if (disabled) return;
    setIsOpen((prev) => !prev);
    setFocusedIndex(-1);
  }

  function handleSelect(optionValue: string) {
    const next = selectedOptions.includes(optionValue)
      ? selectedOptions.filter((item) => item !== optionValue)
      : [...selectedOptions, optionValue];
    updateSelection(next);
  }

  return (
    <div className='w-full' ref={dropdownRef}>
      <label className='mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400' id={`${label}-label`}>
        {label}
      </label>
      <div className='relative z-20 inline-block w-full'>
        <div className='relative flex flex-col items-center'>
          <div
            onClick={toggleDropdown}
            className='w-full'
            aria-expanded={isOpen}
            aria-haspopup='listbox'
            aria-labelledby={`${label}-label`}
            aria-disabled={disabled}
            tabIndex={disabled ? -1 : 0}
          >
            <div
              className={`mb-2 flex min-h-11 rounded-lg border border-gray-300 px-3 py-1.5 shadow-theme-xs outline-hidden transition focus:border-brand-300 focus:shadow-focus-ring dark:border-gray-700 dark:bg-gray-900 dark:focus:border-brand-300 ${
                disabled ? 'cursor-not-allowed bg-gray-50 opacity-50 dark:bg-gray-800' : 'cursor-pointer'
              }`}
            >
              <div className='flex flex-auto flex-wrap gap-2'>
                {selectedOptions.length > 0 ? (
                  selectedOptions.map((item) => {
                    const text = options.find((opt) => opt.value === item)?.text || item;
                    return (
                      <div
                        key={item}
                        className='group flex items-center justify-center rounded-full border-[0.7px] border-transparent bg-gray-100 py-1 ps-2.5 pe-2 text-sm text-gray-800 hover:border-gray-200 dark:bg-gray-800 dark:text-white/90 dark:hover:border-gray-800'
                      >
                        <span className='max-w-full flex-initial'>{text}</span>
                        <button
                          type='button'
                          onClick={(event) => {
                            event.stopPropagation();
                            if (!disabled) updateSelection(selectedOptions.filter((valueItem) => valueItem !== item));
                          }}
                          disabled={disabled}
                          className='cursor-pointer ps-2 text-gray-500 group-hover:text-gray-400 disabled:cursor-not-allowed dark:text-gray-400'
                        >
                          <svg className='fill-current' width='14' height='14' viewBox='0 0 14 14'>
                            <path
                              fillRule='evenodd'
                              clipRule='evenodd'
                              d='M3.40717 4.46881C3.11428 4.17591 3.11428 3.70104 3.40717 3.40815C3.70006 3.11525 4.17494 3.11525 4.46783 3.40815L6.99943 5.93975L9.53095 3.40822C9.82385 3.11533 10.2987 3.11533 10.5916 3.40822C10.8845 3.70112 10.8845 4.17599 10.5916 4.46888L8.06009 7.00041L10.5916 9.53193C10.8845 9.82482 10.8845 10.2997 10.5916 10.5926C10.2987 10.8855 9.82385 10.8855 9.53095 10.5926L6.99943 8.06107L4.46783 10.5927C4.17494 10.8856 3.70006 10.8856 3.40717 10.5927C3.11428 10.2998 3.11428 9.8249 3.40717 9.53201L5.93877 7.00041L3.40717 4.46881Z'
                            />
                          </svg>
                        </button>
                      </div>
                    );
                  })
                ) : (
                  <div className='pointer-events-none h-full w-full p-1 pe-2 text-sm text-gray-400 dark:text-gray-500'>
                    {placeholder}
                  </div>
                )}
              </div>
              <div className='flex w-7 items-center self-start px-1 py-1'>
                <button
                  type='button'
                  onClick={(event) => {
                    event.stopPropagation();
                    toggleDropdown();
                  }}
                  disabled={disabled}
                  className='h-5 w-5 cursor-pointer text-gray-700 outline-hidden disabled:cursor-not-allowed dark:text-gray-400'
                >
                  <svg
                    className={`stroke-current transition-transform ${isOpen ? 'rotate-180' : ''}`}
                    width='20'
                    height='20'
                    viewBox='0 0 20 20'
                    fill='none'
                  >
                    <path
                      d='M4.79175 7.39551L10.0001 12.6038L15.2084 7.39551'
                      stroke='currentColor'
                      strokeWidth='1.5'
                      strokeLinecap='round'
                      strokeLinejoin='round'
                    />
                  </svg>
                </button>
              </div>
            </div>
          </div>
          {isOpen ? (
            <div
              className='max-h-select absolute inset-s-0 top-full z-40 w-full overflow-y-auto rounded-lg bg-white shadow-sm dark:bg-gray-900'
              role='listbox'
              aria-label={label}
            >
              {options.map((option, index) => (
                <div
                  key={option.value}
                  className={`w-full cursor-pointer rounded-t border-b border-gray-200 hover:bg-primary/5 dark:border-gray-800 ${
                    index === focusedIndex ? 'bg-primary/5' : ''
                  } ${selectedOptions.includes(option.value) ? 'bg-primary/10' : ''}`}
                  onClick={() => handleSelect(option.value)}
                  role='option'
                  aria-selected={selectedOptions.includes(option.value)}
                >
                  <div className='relative flex w-full items-center p-2'>
                    <div className='mx-2 leading-6 text-gray-800 dark:text-white/90'>{option.text}</div>
                  </div>
                </div>
              ))}
            </div>
          ) : null}
        </div>
      </div>
    </div>
  );
}
