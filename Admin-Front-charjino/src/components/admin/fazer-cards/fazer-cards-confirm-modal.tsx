'use client';

import { Button, Modal } from '@/components/ui';

type FazerCardsConfirmModalProps = {
  isOpen: boolean;
  title: string;
  message: string;
  busy?: boolean | string | null;
  onClose: () => void;
  onConfirm: () => void;
};

export function FazerCardsConfirmModal({
  isOpen,
  title,
  message,
  busy = false,
  onClose,
  onConfirm
}: FazerCardsConfirmModalProps) {
  const waiting = Boolean(busy);
  return (
    <Modal isOpen={isOpen} onClose={onClose} className='max-w-150 p-5 lg:p-10'>
      <h4 className='mb-4 text-title-sm font-semibold text-gray-800 dark:text-white/90'>{title}</h4>
      <p className='text-sm leading-6 text-gray-500 dark:text-gray-400'>{message}</p>
      <div className='mt-8 flex w-full items-center justify-end gap-3'>
        <Button size='sm' variant='outline' disabled={waiting} onClick={onClose}>
          انصراف
        </Button>
        <Button size='sm' loading={waiting} onClick={onConfirm}>
          تأیید می‌کنم
        </Button>
      </div>
    </Modal>
  );
}
