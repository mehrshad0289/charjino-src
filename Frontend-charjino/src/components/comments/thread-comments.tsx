'use client';

import { useEffect, useRef, useState } from 'react';
import { useFormik } from 'formik';
import * as Yup from 'yup';
import { Button } from '@/components/ui';
import { shopTextareaClass } from '@/components/ui/field';
import { HeartIcon, SendIcon } from '@/components/ui/icon';
import { formatFaDate, toFaDigits } from '@/lib/fa';
import type { BlogComment } from '@/types/blog';

const schema = Yup.object({
  body: Yup.string().trim().required('متن نظر را بنویس').min(3, 'حداقل ۳ کاراکتر').max(1000, 'حداکثر ۱۰۰۰ کاراکتر'),
});

const DRAFT_KEY = 'charjino.comment-draft';

type CommentDraft = {
  path: string;
  body: string;
  parentId: number | null;
  pending: boolean;
};

export type ThreadComment = BlogComment;

type SubmitResult =
  | { ok: true; data: ThreadComment }
  | { ok: false; message: string; needAuth?: boolean };

type LikeResult =
  | { ok: true; data: { liked: boolean; likesCount: number } }
  | { ok: false; message: string; needAuth?: boolean };

type ThreadCommentsProps = {
  signedIn: boolean;
  initialItems: ThreadComment[];
  loginHref: string;
  onSubmit: (body: string, parentId?: number) => Promise<SubmitResult>;
  onLike: (id: number) => Promise<LikeResult>;
};

function currentPath() {
  return window.location.pathname;
}

function readDraft(): CommentDraft | null {
  try {
    const raw = sessionStorage.getItem(DRAFT_KEY);
    if (!raw) return null;
    const draft = JSON.parse(raw) as CommentDraft;
    if (!draft || typeof draft.body !== 'string' || typeof draft.path !== 'string') return null;
    return draft;
  } catch {
    return null;
  }
}

function writeDraft(draft: CommentDraft) {
  try {
    sessionStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
  } catch {
    /* private mode */
  }
}

function clearDraft() {
  try {
    sessionStorage.removeItem(DRAFT_KEY);
  } catch {
    /* private mode */
  }
}

function threadCount(items: ThreadComment[]) {
  return items.reduce((total, comment) => total + 1 + (comment.replies?.length ?? 0), 0);
}

function authorInitial(name?: string) {
  const trimmed = (name ?? '').trim();
  return trimmed ? trimmed.slice(0, 1) : 'ک';
}

export function ThreadComments({ signedIn, initialItems, loginHref, onSubmit, onLike }: ThreadCommentsProps) {
  const [items, setItems] = useState(initialItems);
  const [replyTo, setReplyTo] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const restored = useRef(false);

  const formik = useFormik({
    initialValues: { body: '' },
    validationSchema: schema,
    onSubmit: async (values, helpers) => {
      const body = values.body.trim();
      if (!signedIn) {
        writeDraft({
          path: currentPath(),
          body,
          parentId: replyTo,
          pending: true,
        });
        window.location.assign(loginHref);
        return;
      }
      const applied = await publish(body, replyTo ?? undefined);
      helpers.setSubmitting(false);
      if (applied) {
        setReplyTo(null);
        helpers.resetForm();
      }
    },
  });

  async function publish(body: string, parentId?: number) {
    setError(null);
    setNotice(null);
    const result = await onSubmit(body, parentId);
    if (!result.ok) {
      if (result.needAuth) {
        writeDraft({
          path: currentPath(),
          body,
          parentId: parentId ?? null,
          pending: true,
        });
        window.location.assign(loginHref);
        return false;
      }
      setError(result.message);
      return false;
    }
    clearDraft();
    if (result.data.status === 'approved') {
      if (parentId) {
        setItems((current) =>
          current.map((comment) =>
            comment.id === parentId
              ? { ...comment, replies: [...(comment.replies ?? []), result.data] }
              : comment,
          ),
        );
      } else {
        setItems((current) => [result.data, ...current]);
      }
    } else {
      setNotice('نظر شما ثبت شد و پس از تأیید نمایش داده می‌شود.');
    }
    return true;
  }

  useEffect(() => {
    setItems(initialItems);
  }, [initialItems]);

  useEffect(() => {
    if (restored.current) return;
    const draft = readDraft();
    if (!draft || draft.path !== currentPath()) return;
    restored.current = true;
    if (draft.parentId) setReplyTo(draft.parentId);
    if (!signedIn) {
      void formik.setFieldValue('body', draft.body);
      return;
    }
    if (draft.pending && draft.body.trim().length >= 3) {
      clearDraft();
      void formik.setFieldValue('body', draft.body);
      void (async () => {
        const applied = await publish(draft.body.trim(), draft.parentId ?? undefined);
        if (applied) {
          setReplyTo(null);
          formik.resetForm();
        }
      })();
      return;
    }
    void formik.setFieldValue('body', draft.body);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [signedIn]);

  function goLogin() {
    window.location.assign(loginHref);
  }

  async function like(id: number) {
    if (!signedIn) {
      goLogin();
      return;
    }
    const result = await onLike(id);
    if (!result.ok) {
      if (result.needAuth) {
        goLogin();
        return;
      }
      setError(result.message);
      return;
    }
    setItems((current) => current.map((comment) => patchLike(comment, id, result.data.liked, result.data.likesCount)));
  }

  const bodyError = formik.touched.body ? formik.errors.body : '';
  const count = threadCount(items);

  return (
    <section id="comments" className="flex w-full max-w-3xl flex-col gap-5">
      <div className="flex items-end justify-between gap-3">
        <h2 className="text-lg font-bold text-text">نظرها</h2>
        {count > 0 ? (
          <p className="text-xs text-text-muted">{toFaDigits(count)} نظر</p>
        ) : null}
      </div>

      <form
        className="flex flex-col gap-3 rounded-2xl border border-border bg-card p-4 shadow-card sm:p-5"
        onSubmit={formik.handleSubmit}
      >
        <div className="flex items-center justify-between gap-3">
          <label htmlFor="comment-body" className="text-sm font-bold text-text">
            {replyTo ? 'ارسال پاسخ' : 'ثبت نظر'}
          </label>
          {replyTo ? (
            <button type="button" className="text-xs text-primary-600" onClick={() => setReplyTo(null)}>
              انصراف از پاسخ
            </button>
          ) : null}
        </div>
        <textarea
          id="comment-body"
          className={shopTextareaClass(Boolean(bodyError))}
          rows={4}
          maxLength={1000}
          placeholder={replyTo ? 'پاسخ خود را بنویسید…' : 'نظر خود را درباره این محصول بنویسید…'}
          value={formik.values.body}
          onChange={(event) => void formik.setFieldValue('body', event.target.value)}
          onBlur={() => void formik.setFieldTouched('body', true)}
        />
        {bodyError ? <p className="text-xs text-error-500">{bodyError}</p> : null}
        {notice ? <p className="text-sm text-primary-600">{notice}</p> : null}
        {error ? <p className="text-sm text-error-500">{error}</p> : null}
        <div className="flex flex-col-reverse items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-center text-xs text-text-muted sm:text-start">
            {toFaDigits(formik.values.body.length)} / {toFaDigits(1000)}
          </p>
          <Button
            type="submit"
            size="M"
            loading={formik.isSubmitting}
            icon={<SendIcon size={16} />}
            className="w-full sm:w-auto sm:min-w-32"
          >
            {replyTo ? 'ارسال پاسخ' : 'ثبت نظر'}
          </Button>
        </div>
      </form>

      {items.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-border bg-card/60 px-4 py-8 text-center">
          <p className="text-sm font-medium text-text">هنوز نظری ثبت نشده</p>
          <p className="mt-1 text-xs leading-6 text-text-muted">اولین نظر را شما بنویسید.</p>
        </div>
      ) : (
        <ul className="flex flex-col gap-3">
          {items.map((comment) => (
            <li key={comment.id} className="rounded-2xl border border-border bg-card p-4 shadow-card sm:p-5">
              <CommentBody comment={comment} onReply={setReplyTo} onLike={like} />
              {(comment.replies ?? []).length > 0 ? (
                <ul className="mt-4 flex flex-col gap-3 border-r-2 border-primary-600/25 pr-3 sm:pr-4">
                  {(comment.replies ?? []).map((reply) => (
                    <li key={reply.id}>
                      <CommentBody comment={reply} onLike={like} />
                    </li>
                  ))}
                </ul>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

function CommentBody({
  comment,
  onReply,
  onLike,
}: {
  comment: ThreadComment;
  onReply?: (id: number) => void;
  onLike: (id: number) => void;
}) {
  const name = comment.author?.name ?? 'کاربر';
  return (
    <div className="flex items-start gap-3">
      <span
        aria-hidden
        className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-primary-600/15 text-sm font-bold text-primary-600"
      >
        {authorInitial(name)}
      </span>
      <div className="min-w-0 flex-1">
        <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
          <span className="text-sm font-semibold text-text">{name}</span>
          <span className="text-[11px] text-text-muted">{formatFaDate(comment.createdAt)}</span>
        </div>
        <p className="mt-2 whitespace-pre-wrap text-sm leading-7 text-text">{comment.body}</p>
        <div className="mt-3 flex items-center gap-1">
          <button
            type="button"
            className={`inline-flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-xs transition-colors hover:bg-text/8 ${
              comment.liked ? 'text-primary-600' : 'text-text-muted'
            }`}
            onClick={() => onLike(comment.id)}
          >
            <HeartIcon size={16} />
            {toFaDigits(comment.likesCount)}
          </button>
          {onReply && !comment.parentId ? (
            <button
              type="button"
              className="inline-flex h-8 items-center rounded-lg px-2.5 text-xs text-text-muted transition-colors hover:bg-text/8 hover:text-text"
              onClick={() => onReply(comment.id)}
            >
              پاسخ
            </button>
          ) : null}
        </div>
      </div>
    </div>
  );
}

function patchLike(comment: ThreadComment, id: number, liked: boolean, likesCount: number): ThreadComment {
  if (comment.id === id) {
    return { ...comment, liked, likesCount };
  }
  return {
    ...comment,
    replies: (comment.replies ?? []).map((reply) => patchLike(reply, id, liked, likesCount)),
  };
}
