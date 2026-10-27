'use client';

import { useEffect, useState } from 'react';

const STORAGE_KEY = 'charjino-theme';
const LIGHT = '#f4f7fb';
const DARK = '#171717';

function SunIcon() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
      <circle cx="12" cy="12" r="4" stroke="currentColor" strokeWidth="1.5" />
      <path
        d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
      />
    </svg>
  );
}

function MoonIcon() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path
        d="M20 14.5A7.5 7.5 0 0 1 9.5 4 7.2 7.2 0 1 0 20 14.5Z"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinejoin="round"
      />
    </svg>
  );
}

type ThemeToggleProps = {
  variant?: 'icon' | 'menu';
};

export function ThemeToggle({ variant = 'icon' }: ThemeToggleProps) {
  const [theme, setTheme] = useState<'light' | 'dark'>('dark');

  useEffect(() => {
    setTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');
  }, []);

  function apply(next: 'light' | 'dark') {
    document.documentElement.setAttribute('data-theme', next);
    document.documentElement.style.colorScheme = next;
    localStorage.setItem(STORAGE_KEY, next);
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', next === 'dark' ? DARK : LIGHT);
    setTheme(next);
  }

  const dark = theme === 'dark';
  const label = dark ? 'حالت روشن' : 'حالت تیره';

  if (variant === 'menu') {
    return (
      <button
        type="button"
        aria-label={label}
        aria-pressed={dark}
        onClick={() => apply(dark ? 'light' : 'dark')}
        className="group flex w-full items-center gap-2 rounded-lg px-3 py-1.5 text-start hover:bg-text/8"
      >
        {dark ? <SunIcon /> : <MoonIcon />}
        <span>{label}</span>
      </button>
    );
  }

  return (
    <button
      type="button"
      aria-label={dark ? 'حالت روشن' : 'حالت تیره'}
      aria-pressed={dark}
      onClick={() => apply(dark ? 'light' : 'dark')}
      className="flex items-center justify-center rounded-lg border border-border/60 bg-transparent px-2.5 py-2 transition-all duration-300 ease-in-out hover:bg-text/5"
    >
      {dark ? <SunIcon /> : <MoonIcon />}
    </button>
  );
}
