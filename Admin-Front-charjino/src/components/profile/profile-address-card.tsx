'use client';

import { PROFILE_ADDRESS_EDIT_BUTTON_CLASS, PROFILE_COPY } from '@/data/profile';
import { PencilIcon } from '@/components/ui/icon';
import { useModal } from '@/hooks/useModal';
import type { ProfileAddress } from '@/types/profile';

import { ProfileEditAddressModal } from './profile-edit-address-modal';
import { ProfileInfoField } from './profile-info-field';

type ProfileAddressCardProps = {
  address: ProfileAddress;
  onSave: (address: ProfileAddress) => void;
};

export function ProfileAddressCard({ address, onSave }: ProfileAddressCardProps) {
  const { isOpen, openModal, closeModal } = useModal();

  return (
    <>
      <div className='rounded-2xl border border-gray-200 p-5 lg:p-6 dark:border-gray-800'>
        <div className='flex flex-col gap-6 sm:flex-row lg:items-start lg:justify-between'>
          <div className='flex-1'>
            <h4 className='mb-4 text-lg font-semibold text-gray-800 lg:mb-6 dark:text-white/90'>
              {PROFILE_COPY.address}
            </h4>
            <div className='grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-7 2xl:gap-x-32'>
              <ProfileInfoField label={PROFILE_COPY.country} className=''>
                {address.country}
              </ProfileInfoField>
              <ProfileInfoField label={PROFILE_COPY.city} className=''>
                {address.city}
              </ProfileInfoField>
              <ProfileInfoField label={PROFILE_COPY.postalCode} className=''>
                {address.postalCode}
              </ProfileInfoField>
              <ProfileInfoField label={PROFILE_COPY.taxId} className=''>
                {address.taxId}
              </ProfileInfoField>
            </div>
          </div>
          <div>
            <button type='button' onClick={openModal} className={PROFILE_ADDRESS_EDIT_BUTTON_CLASS}>
              <PencilIcon className='size-5' />
              {PROFILE_COPY.edit}
            </button>
          </div>
        </div>
      </div>
      <ProfileEditAddressModal isOpen={isOpen} address={address} onClose={closeModal} onSave={onSave} />
    </>
  );
}
