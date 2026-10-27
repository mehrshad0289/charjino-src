'use client';

import { PROFILE_COPY } from '@/data/profile';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/ui/field';
import { Modal } from '@/components/ui/modal';
import type { ProfileAddress } from '@/types/profile';
import { useEffect, useState } from 'react';

type ProfileEditAddressModalProps = {
  isOpen: boolean;
  address: ProfileAddress;
  onClose: () => void;
  onSave: (address: ProfileAddress) => void;
};

export function ProfileEditAddressModal({
  isOpen,
  address,
  onClose,
  onSave
}: ProfileEditAddressModalProps) {
  const [draft, setDraft] = useState(address);

  useEffect(() => {
    if (isOpen) setDraft(address);
  }, [isOpen, address]);

  function setField<K extends keyof ProfileAddress>(key: K, value: ProfileAddress[K]) {
    setDraft((current) => ({ ...current, [key]: value }));
  }

  return (
    <Modal isOpen={isOpen} onClose={onClose} className='m-4 max-w-[700px]'>
      <div className='no-scrollbar relative w-full overflow-y-auto rounded-3xl bg-white p-4 lg:p-11 dark:bg-gray-900'>
        <div className='px-2 pe-14'>
          <h4 className='mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90'>{PROFILE_COPY.editAddressTitle}</h4>
          <p className='mb-6 text-sm text-gray-500 lg:mb-7 dark:text-gray-400'>{PROFILE_COPY.editHint}</p>
        </div>
        <form
          className='flex flex-col'
          onSubmit={(event) => {
            event.preventDefault();
            onSave(draft);
            onClose();
          }}
        >
          <div className='custom-scrollbar overflow-y-auto px-2'>
            <div className='grid grid-cols-1 gap-x-6 gap-y-5 lg:grid-cols-2'>
              <TextField
                id='profile-country'
                label={PROFILE_COPY.country}
                placeholder=''
                value={draft.country}
                onChange={(value) => setField('country', value)}
              />
              <TextField
                id='profile-city'
                label={PROFILE_COPY.city}
                placeholder=''
                value={draft.city}
                onChange={(value) => setField('city', value)}
              />
              <TextField
                id='profile-postal'
                label={PROFILE_COPY.postalCode}
                placeholder=''
                value={draft.postalCode}
                onChange={(value) => setField('postalCode', value)}
              />
              <TextField
                id='profile-tax'
                label={PROFILE_COPY.taxId}
                placeholder=''
                value={draft.taxId}
                onChange={(value) => setField('taxId', value)}
              />
            </div>
          </div>
          <div className='mt-6 flex items-center gap-3 px-2 lg:justify-end'>
            <Button size='sm' variant='outline' type='button' onClick={onClose}>
              {PROFILE_COPY.close}
            </Button>
            <Button size='sm' type='submit'>
              {PROFILE_COPY.save}
            </Button>
          </div>
        </form>
      </div>
    </Modal>
  );
}
