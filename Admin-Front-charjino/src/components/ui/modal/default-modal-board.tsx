'use client';

import { COMMON_COPY } from '@/data/ecommerce';
import { Button } from '@/components/ui/button';
import { ComponentCard } from '@/components/ui/component-card';
import { Modal } from '@/components/ui/modal';
import { useModal } from '@/hooks/useModal';

export function DefaultModalBoard() {
  const { isOpen, openModal, closeModal } = useModal();

  function handleSave() {
    closeModal();
  }

  return (
    <ComponentCard title='مودال پیش‌فرض'>
      <Button size='sm' onClick={openModal}>
        {COMMON_COPY.openModal}
      </Button>
      <Modal isOpen={isOpen} onClose={closeModal} className='max-w-150 p-5 lg:p-10'>
        <h4 className='mb-7 text-title-sm font-semibold text-gray-800 dark:text-white/90'>عنوان مودال</h4>
        <p className='text-sm leading-6 text-gray-500 dark:text-gray-400'>
          این یک متن نمونه برای بدنهٔ مودال است. می‌توانید محتوا را با دادهٔ واقعی جایگزین کنید.
        </p>
        <p className='mt-5 text-sm leading-6 text-gray-500 dark:text-gray-400'>
          این طرح از قالب ادمین گرفته شده و فقط برچسب‌ها فارسی شده‌اند.
        </p>
        <div className='mt-8 flex w-full items-center justify-end gap-3'>
          <Button size='sm' variant='outline' onClick={closeModal}>
            {COMMON_COPY.close}
          </Button>
          <Button size='sm' onClick={handleSave}>
            {COMMON_COPY.saveChanges}
          </Button>
        </div>
      </Modal>
    </ComponentCard>
  );
}
