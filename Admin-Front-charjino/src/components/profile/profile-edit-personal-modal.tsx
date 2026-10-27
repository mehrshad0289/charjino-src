'use client';

import { PROFILE_COPY } from '@/data/profile';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/ui/field';
import { Modal } from '@/components/ui/modal';
import type { ProfilePersonal } from '@/types/profile';
import { useEffect, useState } from 'react';

import { ProfilePhoto } from './profile-photo';

type ProfileEditPersonalModalProps = {
  isOpen: boolean;
  personal: ProfilePersonal;
  onClose: () => void;
  onSave: (personal: ProfilePersonal) => void;
};

export function ProfileEditPersonalModal({
  isOpen,
  personal,
  onClose,
  onSave
}: ProfileEditPersonalModalProps) {
  const [draft, setDraft] = useState(personal);

  useEffect(() => {
    if (isOpen) setDraft(personal);
  }, [isOpen, personal]);

  function setField<K extends keyof ProfilePersonal>(key: K, value: ProfilePersonal[K]) {
    setDraft((current) => ({ ...current, [key]: value }));
  }

  return (
    <Modal isOpen={isOpen} onClose={onClose} className='m-4 max-w-[700px]'>
      <div className='no-scrollbar relative w-full max-w-[700px] overflow-y-auto rounded-3xl bg-white p-4 lg:p-11 dark:bg-gray-900'>
        <div className='px-2 pe-14'>
          <h4 className='mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90'>
            {PROFILE_COPY.editPersonalTitle}
          </h4>
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
          <div className='custom-scrollbar h-[450px] overflow-y-auto px-2 pb-3'>
            <div>
              <h4 className='mb-6 text-lg font-medium text-gray-800 dark:text-white/90'>{PROFILE_COPY.changePhoto}</h4>
              <div className='mb-6 flex max-w-sm items-center gap-6 lg:pe-5'>
                <div className='relative size-20 shrink-0 rounded-full sm:size-25'>
                  <ProfilePhoto
                    src={draft.avatar}
                    alt={PROFILE_COPY.changePhoto}
                    width={100}
                    height={100}
                    className='size-20 rounded-full object-cover sm:size-25'
                  />
                  <label
                    htmlFor='profile-file-upload'
                    className='absolute end-0 bottom-0 flex size-8 cursor-pointer items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400'
                  >
                    <input
                      type='file'
                      name='file-upload'
                      id='profile-file-upload'
                      accept='image/jpeg,image/png'
                      className='hidden'
                      onChange={(event) => {
                        const file = event.target.files?.[0];
                        if (!file) return;
                        const next = URL.createObjectURL(file);
                        setDraft((current) => {
                          if (current.avatar.startsWith('blob:')) URL.revokeObjectURL(current.avatar);
                          return { ...current, avatar: next };
                        });
                      }}
                    />
                    <svg width='20' height='20' viewBox='0 0 20 20' fill='none' aria-hidden>
                      <path
                        d='M12.6731 3.41904C12.4371 3.10308 12.0659 2.91699 11.6715 2.91699H8.32809C7.93374 2.91699 7.56252 3.10308 7.32656 3.41904L6.83173 4.08164C6.59576 4.3976 6.22454 4.58369 5.83019 4.58369H3.5415C2.85115 4.58369 2.2915 5.14333 2.2915 5.83369V14.3754C2.2915 15.0657 2.85115 15.6254 3.5415 15.6254H16.4582C17.1485 15.6254 17.7082 15.0657 17.7082 14.3754V5.83369C17.7082 5.14333 17.1485 4.58369 16.4582 4.58369H14.1694C13.7751 4.58369 13.4039 4.3976 13.1679 4.08164L12.6731 3.41904Z'
                        stroke='currentColor'
                        strokeWidth='1.5'
                        strokeLinecap='round'
                        strokeLinejoin='round'
                      />
                      <path
                        d='M13.3332 9.79362C13.3332 11.6346 11.8408 13.127 9.99984 13.127C8.15889 13.127 6.6665 11.6346 6.6665 9.79362C6.6665 7.95267 8.15889 6.46029 9.99984 6.46029C11.8408 6.46029 13.3332 7.95267 13.3332 9.79362Z'
                        stroke='currentColor'
                        strokeWidth='1.5'
                        strokeLinecap='round'
                        strokeLinejoin='round'
                      />
                    </svg>
                  </label>
                </div>
                <div>
                  <p className='text-sm text-gray-500 dark:text-gray-400'>{PROFILE_COPY.photoHint}</p>
                </div>
              </div>
            </div>
            <div className='my-7'>
              <h5 className='mb-5 text-lg font-medium text-gray-800 lg:mb-6 dark:text-white/90'>
                {PROFILE_COPY.personalInfo}
              </h5>
              <div className='grid grid-cols-1 gap-x-6 gap-y-5 lg:grid-cols-2'>
                <div className='col-span-2 lg:col-span-1'>
                  <TextField
                    id='profile-first-name'
                    label={PROFILE_COPY.firstName}
                    placeholder=''
                    value={draft.firstName}
                    onChange={(value) => setField('firstName', value)}
                  />
                </div>
                <div className='col-span-2 lg:col-span-1'>
                  <TextField
                    id='profile-last-name'
                    label={PROFILE_COPY.lastName}
                    placeholder=''
                    value={draft.lastName}
                    onChange={(value) => setField('lastName', value)}
                  />
                </div>
                <div className='col-span-2 lg:col-span-1'>
                  <TextField
                    id='profile-email'
                    type='email'
                    label={PROFILE_COPY.email}
                    placeholder=''
                    value={draft.email}
                    onChange={(value) => setField('email', value)}
                  />
                </div>
                <div className='col-span-2 lg:col-span-1'>
                  <TextField
                    id='profile-phone'
                    label={PROFILE_COPY.phone}
                    placeholder=''
                    value={draft.phone}
                    onChange={(value) => setField('phone', value)}
                  />
                </div>
                <div className='col-span-2'>
                  <TextField
                    id='profile-bio'
                    label={PROFILE_COPY.bio}
                    placeholder=''
                    value={draft.bio}
                    onChange={(value) => setField('bio', value)}
                  />
                </div>
              </div>
            </div>
            <div>
              <h5 className='mb-5 text-lg font-medium text-gray-800 lg:mb-6 dark:text-white/90'>{PROFILE_COPY.social}</h5>
              <div className='grid grid-cols-1 gap-x-6 gap-y-5 lg:grid-cols-2'>
                <TextField
                  id='profile-facebook'
                  label={PROFILE_COPY.facebook}
                  placeholder=''
                  value={draft.facebook}
                  onChange={(value) => setField('facebook', value)}
                />
                <TextField
                  id='profile-twitter'
                  label={PROFILE_COPY.twitter}
                  placeholder=''
                  value={draft.twitter}
                  onChange={(value) => setField('twitter', value)}
                />
                <TextField
                  id='profile-linkedin'
                  label={PROFILE_COPY.linkedin}
                  placeholder=''
                  value={draft.linkedin}
                  onChange={(value) => setField('linkedin', value)}
                />
                <TextField
                  id='profile-instagram'
                  label={PROFILE_COPY.instagram}
                  placeholder=''
                  value={draft.instagram}
                  onChange={(value) => setField('instagram', value)}
                />
              </div>
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
