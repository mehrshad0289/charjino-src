export type CommentModerationStatus = 'pending' | 'approved' | 'hidden';

export const COMMENT_STATUS_FILTERS = [
  { value: 'pending', label: 'در انتظار تأیید' },
  { value: 'approved', label: 'تأییدشده' },
  { value: 'hidden', label: 'پنهان' },
  { value: 'all', label: 'همه' }
] as const;

export function commentStatusLabel(status: string) {
  if (status === 'pending') return 'در انتظار تأیید';
  if (status === 'approved') return 'تأییدشده';
  if (status === 'hidden') return 'پنهان';
  return status;
}

export function commentStatusBadgeColor(status: string): 'warning' | 'success' | 'light' {
  if (status === 'pending') return 'warning';
  if (status === 'approved') return 'success';
  return 'light';
}
