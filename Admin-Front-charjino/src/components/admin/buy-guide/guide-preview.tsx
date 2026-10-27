'use client';

type GuidePreviewProps = {
  data: Record<string, unknown> | null;
};

export function GuidePreview({ data }: GuidePreviewProps) {
  if (!data) {
    return <p className='text-theme-sm text-gray-500'>یک محصول نمونه انتخاب کنید.</p>;
  }
  const sections = Array.isArray(data.sections) ? data.sections : [];
  return (
    <div className='space-y-4 text-sm leading-7 text-gray-700 dark:text-white/80' dir='rtl'>
      <h3 className='text-base font-semibold text-gray-900 dark:text-white'>{String(data.title ?? 'راهنمای خرید')}</h3>
      {data.summary ? <p>{String(data.summary)}</p> : null}
      {data.introHtml ? (
        <div dangerouslySetInnerHTML={{ __html: String(data.introHtml) }} />
      ) : null}
      {sections.map((section, index) => {
        const row = section as { type?: string; items?: { q?: string; a?: string }[] };
        return (
          <section key={`${row.type}-${index}`} className='rounded-xl bg-gray-50 p-3 dark:bg-white/5'>
            <p className='mb-2 text-theme-xs font-medium text-gray-500'>{row.type}</p>
            {row.type === 'faq' && Array.isArray(row.items)
              ? row.items.map((item) => (
                  <div key={item.q}>
                    <strong>{item.q}</strong>
                    <p>{item.a}</p>
                  </div>
                ))
              : (
                <pre className='whitespace-pre-wrap text-theme-xs'>{JSON.stringify(section, null, 2)}</pre>
              )}
          </section>
        );
      })}
    </div>
  );
}
