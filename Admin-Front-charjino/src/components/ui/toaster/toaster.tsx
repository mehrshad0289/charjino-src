'use client';

import { Toaster } from 'sonner';
import { useTheme } from '@/hooks/useTheme';

export function AppToaster() {
  const { theme } = useTheme();

  return (
    <Toaster
      theme={theme}
      dir='rtl'
      position='top-center'
      richColors
      closeButton
      duration={4200}
      visibleToasts={4}
      toastOptions={{
        className: 'font-outfit'
      }}
    />
  );
}
