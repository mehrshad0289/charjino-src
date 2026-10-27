'use client';

import { PROFILE_COPY, PROFILE_USER } from '@/data/profile';
import { useProfile } from '@/hooks/useProfile';
import type { ProfileUser } from '@/types/profile';

import { ProfileAddressCard } from './profile-address-card';
import { ProfileDangerZone } from './profile-danger-zone';
import { ProfileMetaCard } from './profile-meta-card';
import { ProfileSecurity } from './profile-security';

type ProfileScreenProps = {
  email?: string;
};

export function ProfileScreen({ email }: ProfileScreenProps) {
  const initial: ProfileUser = email
    ? { ...PROFILE_USER, personal: { ...PROFILE_USER.personal, email } }
    : PROFILE_USER;
  const { profile, savePersonal, saveAddress } = useProfile(initial);

  return (
    <div className='rounded-2xl border border-gray-200 bg-white p-5 lg:p-6 dark:border-gray-800 dark:bg-white/3'>
      <h3 className='mb-5 text-lg font-semibold text-gray-800 lg:mb-7 dark:text-white/90'>{PROFILE_COPY.title}</h3>
      <div className='space-y-6'>
        <ProfileMetaCard personal={profile.personal} onSave={savePersonal} />
        <ProfileAddressCard address={profile.address} onSave={saveAddress} />
        <ProfileSecurity />
        <ProfileDangerZone />
      </div>
    </div>
  );
}
