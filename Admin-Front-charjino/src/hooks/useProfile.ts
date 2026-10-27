'use client';

import { PROFILE_USER } from '@/data/profile';
import type { ProfileAddress, ProfilePersonal, ProfileUser } from '@/types/profile';
import { useCallback, useState } from 'react';

export function useProfile(initial: ProfileUser = PROFILE_USER) {
  const [profile, setProfile] = useState<ProfileUser>(initial);

  const savePersonal = useCallback((personal: ProfilePersonal) => {
    setProfile((current) => ({ ...current, personal }));
  }, []);

  const saveAddress = useCallback((address: ProfileAddress) => {
    setProfile((current) => ({ ...current, address }));
  }, []);

  return { profile, savePersonal, saveAddress };
}
