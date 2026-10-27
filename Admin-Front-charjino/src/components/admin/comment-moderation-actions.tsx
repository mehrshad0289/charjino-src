'use client';

import { Button } from '@/components/ui';
import type { CommentModerationStatus } from '@/lib/comment-moderation';

type CommentModerationActionsProps = {
  status: CommentModerationStatus;
  busy?: boolean;
  onApprove: () => void;
  onHide: () => void;
};

export function CommentModerationActions({ status, busy = false, onApprove, onHide }: CommentModerationActionsProps) {
  return (
    <div className='flex flex-wrap gap-2'>
      {status !== 'approved' ? (
        <Button type='button' size='sm' loading={busy} onClick={onApprove}>
          تأیید کن
        </Button>
      ) : null}
      {status !== 'hidden' ? (
        <Button type='button' size='sm' variant='outline' loading={busy} onClick={onHide}>
          پنهان کن
        </Button>
      ) : null}
    </div>
  );
}
