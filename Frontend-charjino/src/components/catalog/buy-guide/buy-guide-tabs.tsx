'use client';

import { useEffect, useState, type KeyboardEvent, type ReactNode } from 'react';

type Tab = { key: string; label: string; panel: ReactNode };

export function BuyGuideTabs({ tabs, initialKey }: { tabs: Tab[]; initialKey?: string }) {
  const [active, setActive] = useState(tabs[0]?.key ?? '');

  useEffect(() => {
    const fromUrl = new URLSearchParams(window.location.search).get('guide') ?? initialKey;
    if (fromUrl && tabs.some((tab) => tab.key === fromUrl)) {
      setActive(fromUrl);
    }
  }, [initialKey, tabs]);

  function onKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    const index = tabs.findIndex((tab) => tab.key === active);
    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      setActive(tabs[Math.min(tabs.length - 1, index + 1)]?.key ?? active);
    }
    if (event.key === 'ArrowRight') {
      event.preventDefault();
      setActive(tabs[Math.max(0, index - 1)]?.key ?? active);
    }
  }

  return (
    <div>
      <div role="tablist" aria-label="دستگاه فعال‌سازی" className="mb-3 flex flex-wrap gap-2" onKeyDown={onKeyDown}>
        {tabs.map((tab) => {
          const selected = tab.key === active;
          return (
            <button
              key={tab.key}
              type="button"
              role="tab"
              id={`guide-tab-${tab.key}`}
              aria-selected={selected}
              aria-controls={`guide-panel-${tab.key}`}
              tabIndex={selected ? 0 : -1}
              className={`rounded-full px-3 py-1.5 text-sm ${
                selected ? 'bg-primary-600 text-white' : 'bg-card-muted text-text/70'
              }`}
              onClick={() => setActive(tab.key)}
            >
              {tab.label}
            </button>
          );
        })}
      </div>
      {tabs.map((tab) => (
        <div
          key={tab.key}
          role="tabpanel"
          id={`guide-panel-${tab.key}`}
          aria-labelledby={`guide-tab-${tab.key}`}
          hidden={tab.key !== active}
        >
          {tab.panel}
        </div>
      ))}
    </div>
  );
}
