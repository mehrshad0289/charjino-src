'use client';

import { PROFILE_COPY, PROFILE_EDIT_BUTTON_CLASS, profileDisplayName } from '@/data/profile';
import { PencilIcon } from '@/components/ui/icon';
import { useModal } from '@/hooks/useModal';
import type { ProfilePersonal } from '@/types/profile';

import { ProfileEditPersonalModal } from './profile-edit-personal-modal';
import { ProfileInfoField } from './profile-info-field';
import { ProfilePhoto } from './profile-photo';
import { ProfileSocialLinks } from './profile-social-links';

type ProfileMetaCardProps = {
  personal: ProfilePersonal;
  onSave: (personal: ProfilePersonal) => void;
};

export function ProfileMetaCard({ personal, onSave }: ProfileMetaCardProps) {
  const { isOpen, openModal, closeModal } = useModal();

  return (
    <>
      <div className='mb-6 rounded-2xl border border-gray-200 p-5 lg:p-6 dark:border-gray-800'>
        <div className='flex flex-col gap-5 sm:flex-row xl:gap-10'>
          <div className='flex-1'>
            <div className='mb-4 flex flex-col gap-5 sm:flex-row lg:mb-6 xl:items-center xl:justify-between'>
              <div className='flex w-full flex-col items-start gap-4 sm:flex-row sm:items-center lg:gap-6'>
                <div className='overflow-hidden rounded-full border border-gray-200 dark:border-gray-800'>
                  <ProfilePhoto
                    src={personal.avatar}
                    width={80}
                    height={80}
                    className='size-20'
                    alt={profileDisplayName(personal)}
                  />
                </div>
                <div className='text-start'>
                  <h4 className='mb-2 text-lg font-semibold text-gray-800 dark:text-white/90'>
                    {profileDisplayName(personal)}
                  </h4>
                  <div className='flex items-center gap-1 sm:gap-3'>
                    <p className='text-sm text-gray-500 dark:text-gray-400'>{personal.role}</p>
                    <div className='hidden h-3.5 w-px bg-gray-300 sm:block dark:bg-gray-700' />
                    <p className='text-sm text-gray-500 dark:text-gray-400'>{personal.location}</p>
                  </div>
                </div>
              </div>
            </div>
            <div className='relative grid max-w-4xl grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4 xl:gap-x-11 xl:gap-y-7'>
              <ProfileInfoField label={PROFILE_COPY.firstName}>{personal.firstName}</ProfileInfoField>
              <ProfileInfoField label={PROFILE_COPY.lastName}>{personal.lastName}</ProfileInfoField>
              <div className='hidden xl:block' />
              <div className='hidden xl:block' />
              <ProfileInfoField label={PROFILE_COPY.email} className=''>
                {personal.email}
              </ProfileInfoField>
              <ProfileInfoField label={PROFILE_COPY.phone} className=''>
                {personal.phone}
              </ProfileInfoField>
              <ProfileInfoField label={PROFILE_COPY.bio} className=''>
                {personal.bio}
              </ProfileInfoField>
              <ProfileInfoField label={PROFILE_COPY.social} className=''>
                <ProfileSocialLinks
                  facebook={personal.facebook}
                  twitter={personal.twitter}
                  linkedin={personal.linkedin}
                  instagram={personal.instagram}
                />
              </ProfileInfoField>
            </div>
          </div>
          <div>
            <button type='button' onClick={openModal} className={PROFILE_EDIT_BUTTON_CLASS}>
              <PencilIcon className='size-5' />
              {PROFILE_COPY.edit}
            </button>
          </div>
        </div>
      </div>
      <ProfileEditPersonalModal isOpen={isOpen} personal={personal} onClose={closeModal} onSave={onSave} />
    </>
  );
}
