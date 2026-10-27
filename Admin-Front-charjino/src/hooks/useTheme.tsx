'use client';

import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';

type ThemeMode = 'light' | 'dark' | 'auto';
type ResolvedTheme = 'light' | 'dark';

type ThemeContextType = {
  theme: ResolvedTheme;
  themeMode: ThemeMode;
  setThemeMode: (mode: ThemeMode) => void;
  toggleTheme: () => void;
};

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [themeMode, setThemeModeState] = useState<ThemeMode>('light');
  const [theme, setTheme] = useState<ResolvedTheme>('light');
  const [isInitialized, setIsInitialized] = useState(false);

  useEffect(() => {
    const hasDarkClass = document.documentElement.classList.contains('dark');
    const savedMode = localStorage.getItem('theme-mode') as ThemeMode | null;
    const legacySavedTheme = localStorage.getItem('theme') as ResolvedTheme | null;
    const initialMode = savedMode || (legacySavedTheme as ThemeMode) || (hasDarkClass ? 'dark' : 'light');
    setThemeModeState(initialMode);
    if (initialMode === 'auto') {
      setTheme(window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    } else {
      setTheme(initialMode === 'dark' ? 'dark' : 'light');
    }
    setIsInitialized(true);
  }, []);

  useEffect(() => {
    if (!isInitialized) return;
    localStorage.setItem('theme-mode', themeMode);

    if (themeMode === 'auto') {
      const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
      const handleChange = () => {
        setTheme(mediaQuery.matches ? 'dark' : 'light');
      };
      handleChange();
      mediaQuery.addEventListener('change', handleChange);
      return () => mediaQuery.removeEventListener('change', handleChange);
    }

    setTheme(themeMode as ResolvedTheme);
  }, [themeMode, isInitialized]);

  useEffect(() => {
    if (!isInitialized) return;
    localStorage.setItem('theme', theme);
    if (theme === 'dark') {
      document.documentElement.classList.add('dark');
      document.documentElement.setAttribute('data-color-scheme', 'dark');
    } else {
      document.documentElement.classList.remove('dark');
      document.documentElement.setAttribute('data-color-scheme', 'light');
    }
  }, [theme, isInitialized]);

  return (
    <ThemeContext.Provider
      value={{
        theme,
        themeMode,
        setThemeMode: setThemeModeState,
        toggleTheme: () => setThemeModeState(theme === 'light' ? 'dark' : 'light')
      }}
    >
      {children}
    </ThemeContext.Provider>
  );
}

export function useTheme() {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useTheme must be used within a ThemeProvider');
  }
  return context;
}
