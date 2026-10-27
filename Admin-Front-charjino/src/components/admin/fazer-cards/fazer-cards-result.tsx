'use client';

type FazerCardsResultProps = {
  last: {
    method: string;
    path: string;
    ok: boolean;
    message?: string;
    payload?: unknown;
  } | null;
};

export function FazerCardsResult({ last }: FazerCardsResultProps) {
  if (!last) {
    return <p className='text-theme-sm text-gray-500'>هنوز درخواستی ارسال نشده است.</p>;
  }

  return (
    <pre className='max-h-96 overflow-auto rounded-xl bg-gray-900 p-4 text-xs text-gray-100' dir='ltr'>
      {JSON.stringify(last, null, 2)}
    </pre>
  );
}
