'use client';

import { logout } from '@/actions/auth.actions';
import { PROFILE_ACTION_BUTTON_CLASS, PROFILE_COPY, PROFILE_DELETE_BUTTON_CLASS } from '@/data/profile';

export function ProfileDangerZone() {
  return (
    <div className='mb-6 rounded-2xl border border-gray-200 bg-white p-5 lg:p-6 dark:border-gray-800 dark:bg-white/3'>
      <h4 className='mb-4 text-lg font-semibold text-gray-800 lg:mb-6 dark:text-white/90'>{PROFILE_COPY.danger}</h4>
      <div>
        <div className='flex flex-col justify-between gap-4 border-b border-gray-200 py-4 first:pt-0 last:border-b-0 last:pb-0 sm:flex-row sm:items-end dark:border-gray-800'>
          <div>
            <span className='mb-1 block text-base font-medium text-gray-800 dark:text-white/90'>
              {PROFILE_COPY.logoutAll}
            </span>
            <p className='text-sm text-gray-500 dark:text-gray-400'>{PROFILE_COPY.logoutAllHint}</p>
          </div>
          <div>
            <form action={logout}>
              <button type='submit' className={PROFILE_ACTION_BUTTON_CLASS}>
                <svg
                  className='rtl:-scale-x-100'
                  xmlns='http://www.w3.org/2000/svg'
                  width='20'
                  height='20'
                  viewBox='0 0 20 20'
                  fill='none'
                  aria-hidden
                >
                  <path
                    d='M3.33325 10.0003L9.79159 10.0003M6.66599 6.66699L3.33488 10.0002L6.66599 13.3337M8.12492 4.16374V3.54199C8.12492 2.85164 8.68456 2.29199 9.37492 2.29199H14.3749C15.0653 2.29199 15.6249 2.85164 15.6249 3.54199V16.4587C15.6249 17.149 15.0653 17.7087 14.3749 17.7087H9.37492C8.68456 17.7087 8.12492 17.149 8.12492 16.4587V15.8337'
                    stroke='currentColor'
                    strokeWidth='1.5'
                    strokeLinecap='round'
                    strokeLinejoin='round'
                  />
                </svg>
                {PROFILE_COPY.logout}
              </button>
            </form>
          </div>
        </div>
        <div className='flex flex-col justify-between gap-4 border-b border-gray-200 py-4 first:pt-0 last:border-b-0 last:pb-0 sm:flex-row sm:items-end dark:border-gray-800'>
          <div>
            <span className='mb-1 block text-base font-medium text-gray-800 dark:text-white/90'>
              {PROFILE_COPY.deleteAccount}
            </span>
            <p className='text-sm text-gray-500 dark:text-gray-400'>{PROFILE_COPY.deleteAccountHint}</p>
          </div>
          <div>
            <button type='button' className={PROFILE_DELETE_BUTTON_CLASS}>
              <svg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 20 20' fill='none' aria-hidden>
                <path
                  d='M4.37492 4.79199V16.4587C4.37492 17.149 4.93456 17.7087 5.62492 17.7087H14.3749C15.0653 17.7087 15.6249 17.149 15.6249 16.4587V4.79199M3.33325 4.79199H16.6658M4.37492 13.2466V8.24658M15.6249 13.2466V8.24658M8.33325 13.7503V8.75033M11.6666 13.7503V8.75033M12.7078 4.79199V3.54199C12.7078 2.85164 12.1482 2.29199 11.4578 2.29199H8.54118C7.85082 2.29199 7.29118 2.85164 7.29118 3.54199V4.79199H12.7078Z'
                  stroke='currentColor'
                  strokeWidth='1.5'
                  strokeLinecap='round'
                  strokeLinejoin='round'
                />
              </svg>
              {PROFILE_COPY.deleteAccount}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
