'use client';

import { useEffect, useState } from 'react';
import { Toaster } from 'sonner';

export function AppToaster() {
  const [theme, setTheme] = useState<'light' | 'dark'>('dark');

  useEffect(() => {
    const root = document.documentElement;
    const sync = () => setTheme(root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');
    sync();
    const observer = new MutationObserver(sync);
    observer.observe(root, { attributes: true, attributeFilter: ['data-theme'] });
    return () => observer.disconnect();
  }, []);

  return (
    <Toaster
      theme={theme}
      dir="rtl"
      position="top-center"
      richColors
      closeButton
      duration={4200}
      visibleToasts={4}
      toastOptions={{
        className: 'font-sans',
      }}
    />
  );
}
