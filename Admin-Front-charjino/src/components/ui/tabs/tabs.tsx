'use client';

type TabItem = {
  id: string;
  label: string;
  className?: string;
};

type TabsProps = {
  items: TabItem[];
  value: string;
  onChange: (id: string) => void;
};

export function Tabs({ items, value, onChange }: TabsProps) {
  function buttonClass(id: string) {
    return value === id
      ? 'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800'
      : 'text-gray-500 dark:text-gray-400';
  }

  return (
    <div className='flex max-h-10 items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900'>
      {items.map((item) => (
        <button
          key={item.id}
          type='button'
          onClick={() => onChange(item.id)}
          className={`w-full whitespace-nowrap rounded-md px-3 text-theme-sm font-medium hover:text-gray-900 dark:hover:text-white ${item.className ?? 'py-2'} ${buttonClass(item.id)}`}
        >
          {item.label}
        </button>
      ))}
    </div>
  );
}
