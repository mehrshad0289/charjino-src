'use client';

import { useEffect, useState } from 'react';
import { getSessionUser } from '@/actions/auth.actions';
import { addProductComment, likeProductComment, listProductComments } from '@/actions/product-comments.actions';
import { ThreadComments, type ThreadComment } from '@/components/comments/thread-comments';
import { authHref } from '@/lib/safe-path';
import { ROUTES } from '@/lib/routes';

type ProductCommentsProps = {
  handle: string;
};

export function ProductComments({ handle }: ProductCommentsProps) {
  const [signedIn, setSignedIn] = useState(false);
  const [items, setItems] = useState<ThreadComment[]>([]);
  const [ready, setReady] = useState(false);
  const [listError, setListError] = useState<string | null>(null);
  const loginHref = authHref(`${ROUTES.catalog(handle)}#comments`);

  useEffect(() => {
    let cancelled = false;
    setReady(false);
    void getSessionUser().then((user) => {
      if (cancelled) return;
      setSignedIn(Boolean(user));
      setReady(true);
    });
    void listProductComments(handle).then((comments) => {
      if (cancelled) return;
      if (comments.ok) {
        setItems(comments.data.items);
        setListError(null);
      } else {
        setItems([]);
        setListError(comments.message);
      }
    });
    return () => {
      cancelled = true;
    };
  }, [handle]);

  if (!ready) {
    return (
      <section id="comments" className="flex w-full max-w-3xl flex-col gap-4">
        <h2 className="text-lg font-bold text-text">نظرها</h2>
        <div className="h-40 animate-pulse rounded-2xl border border-border bg-card shadow-card" />
      </section>
    );
  }

  return (
    <div className="flex w-full max-w-3xl flex-col gap-3">
      {listError ? <p className="text-sm text-error-500">{listError}</p> : null}
      <ThreadComments
        key={handle}
        signedIn={signedIn}
        initialItems={items}
        loginHref={loginHref}
        onSubmit={(body, parentId) => addProductComment(handle, body, parentId)}
        onLike={(id) => likeProductComment(id)}
      />
    </div>
  );
}
