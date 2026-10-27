'use client';

import { AUTH_COPY } from '@/data/auth';
import { requestAdminPasswordReset } from '@/actions/auth.actions';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/ui/field';
import { ChevronLeftIcon } from '@/components/ui/icon';
import Link from 'next/link';
import { useState } from 'react';

export function ForgotPasswordScreen() {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  return (
    <div className='flex w-full flex-1 flex-col lg:w-1/2'>
      <div className='mx-auto mb-5 w-full max-w-md sm:pt-10'>
        <Link
          href='/signin'
          className='inline-flex items-center text-sm text-gray-500 transition-colors hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
        >
          <ChevronLeftIcon className='size-5 rtl:rotate-180' />
          {AUTH_COPY.title}
        </Link>
      </div>
      <div className='mx-auto flex w-full max-w-md flex-1 flex-col justify-center'>
        <h1 className='mb-2 text-title-sm font-semibold text-gray-800 sm:text-title-md dark:text-white/90'>
          {AUTH_COPY.forgotTitle}
        </h1>
        <p className='mb-8 text-sm text-gray-500 dark:text-gray-400'>{AUTH_COPY.forgotSubtitle}</p>
        <form
          className='space-y-6'
          onSubmit={(event) => {
            event.preventDefault();
            setLoading(true);
            setError('');
            setMessage('');
            void requestAdminPasswordReset(email).then((result) => {
              setLoading(false);
              if (result.ok) setMessage(result.message);
              else setError(result.message);
            });
          }}
        >
          <TextField
            id='email'
            name='email'
            label={AUTH_COPY.email}
            type='text'
            autoComplete='email'
            placeholder={AUTH_COPY.emailPlaceholder}
            value={email}
            onChange={setEmail}
          />
          {error ? <p className='text-sm text-error-500'>{error}</p> : null}
          {message ? <p className='text-sm text-success-500'>{message}</p> : null}
          <Button className='w-full' size='sm' type='submit' loading={loading}>
            {AUTH_COPY.forgotSubmit}
          </Button>
        </form>
      </div>
    </div>
  );
}
