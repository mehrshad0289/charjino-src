'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useFormik } from 'formik';
import { Button, OtpInput, PhoneField } from '@/components/ui';
import { useAuth } from '@/hooks/useAuth';
import { toFaDigits } from '@/lib/fa';
import { ROUTES } from '@/lib/routes';
import { normalizeIranianMobile, phoneLoginSchema, toIranianMobileNational } from '@/services/validation.service';
import { AuthLayout } from './auth-layout';
import styles from './auth-layout.module.css';
import { safeNextPath } from '@/lib/safe-path';

type Step = 'phone' | 'otp-timer' | 'otp-resend';

const OTP_SECONDS = 120;

type AuthScreenProps = {
  nextPath?: string | null;
  initialPhone?: string | null;
};

export function AuthScreen({ nextPath, initialPhone = '' }: AuthScreenProps) {
  const router = useRouter();
  const destination = safeNextPath(nextPath);
  const { loading, error, requestOtp, verifyOtp, clearError } = useAuth();
  const [step, setStep] = useState<Step>('phone');
  const [otp, setOtp] = useState('');
  const [seconds, setSeconds] = useState(OTP_SECONDS);

  const formik = useFormik({
    enableReinitialize: true,
    initialValues: { phone: toIranianMobileNational(initialPhone ?? '') },
    validationSchema: phoneLoginSchema,
    onSubmit: async (values) => {
      const ok = await requestOtp(values.phone);
      if (!ok) return;
      setOtp('');
      setSeconds(OTP_SECONDS);
      setStep('otp-timer');
    },
  });

  const displayPhone = toFaDigits(normalizeIranianMobile(formik.values.phone) || '09999999999');
  const phoneError = Boolean((formik.touched.phone && formik.errors.phone) || (step === 'phone' && error));

  useEffect(() => {
    if (step !== 'otp-timer') return;
    if (seconds <= 0) {
      setStep('otp-resend');
      return;
    }
    const id = window.setTimeout(() => setSeconds((value) => value - 1), 1000);
    return () => window.clearTimeout(id);
  }, [step, seconds]);

  function goBack() {
    if (step === 'phone') {
      router.push(ROUTES.home);
      return;
    }
    clearError();
    setStep('phone');
    setOtp('');
  }

  async function restartTimer() {
    const ok = await requestOtp(formik.values.phone);
    if (!ok) return;
    setOtp('');
    setSeconds(OTP_SECONDS);
    setStep('otp-timer');
  }

  async function handleVerify() {
    const ok = await verifyOtp(formik.values.phone, otp);
    if (!ok) return;
    router.refresh();
    router.push(destination);
  }

  return (
    <AuthLayout onBack={goBack}>
      {step === 'phone' ? (
        <form
          className="flex flex-col"
          method="post"
          onSubmit={(event) => {
            event.preventDefault();
            void formik.submitForm();
          }}
        >
          <div className="flex flex-col">
            <div className="flex flex-col gap-3 text-start">
              <h1 className={styles.title}>ورود و عضویت</h1>
              <p className={styles.lede}>
                {process.env.NODE_ENV !== 'production'
                  ? `در محیط محلی پیامک ارسال نمی‌شود. بعد از دریافت کد، ${toFaDigits('000000')} را بزنید.`
                  : 'با شماره موبایل وارد شوید؛ رمز اکانت بازی گرفته نمی‌شود. یک کد پیامکی می‌فرستیم.'}
              </p>
            </div>
            <div className="mt-8">
              <PhoneField
                name="phone"
                label="شماره همراه"
                value={formik.values.phone}
                previewState={phoneError ? 'error' : 'default'}
                hint={phoneError ? String(formik.errors.phone ?? error) : 'شماره همراه خود را وارد کنید.'}
                onBlur={() => formik.setFieldTouched('phone', true)}
                onChange={(value) => formik.setFieldValue('phone', value)}
              />
            </div>
          </div>
          <div className="pt-6 lg:pt-8">
            <Button
              size="L"
              color="primary"
              variant="fill"
              type="submit"
              fullWidth
              leftIcon={false}
              rightIcon={false}
              loading={loading}
              disabled={loading}
            >
              دریافت کد
            </Button>
          </div>
        </form>
      ) : (
        <div className="flex flex-col">
          <div className="flex flex-col">
            <div className="flex flex-col gap-3 text-start">
              <h1 className={styles.title}>کد تأیید</h1>
              <p className={styles.lede}>
                کد ارسال‌شده به {displayPhone} را وارد کنید.
                {process.env.NODE_ENV !== 'production' ? (
                  <>
                    {' '}
                    در محیط محلی کد تأیید {toFaDigits('000000')} است.
                  </>
                ) : null}
              </p>
            </div>
            <div className="mt-8">
              <OtpInput
                value={otp}
                onChange={setOtp}
                autoFocus
                previewState={error ? 'error' : 'default'}
                hint={error ?? 'کد را وارد کنید.'}
              />
            </div>
            <div className="mt-6 text-sm font-medium leading-[1.9] text-text-muted">
              {step === 'otp-timer' ? (
                <p>کدی دریافت نکردید؟ دریافت مجدد {toFaDigits(seconds)} ثانیه.</p>
              ) : (
                <div className="flex flex-wrap items-center gap-1">
                  <p>کدی دریافت نکردید؟</p>
                  <Button
                    size="M"
                    color="primary"
                    variant="text"
                    type="button"
                    leftIcon={false}
                    rightIcon={false}
                    className="h-[30px]! min-h-0! px-0! text-sm! font-semibold! text-text!"
                    loading={loading}
                    disabled={loading}
                    onClick={restartTimer}
                  >
                    دریافت مجدد کد
                  </Button>
                </div>
              )}
            </div>
          </div>
          <div className="mt-6 flex flex-col gap-3">
            <Button
              size="L"
              color="primary"
              variant="fill"
              type="button"
              fullWidth
              leftIcon={false}
              rightIcon={false}
              loading={loading}
              disabled={otp.length < 6 || loading}
              onClick={handleVerify}
            >
              تایید
            </Button>
            <Button
              size="L"
              color="primary"
              variant="outline"
              type="button"
              fullWidth
              leftIcon={false}
              rightIcon={false}
              disabled={loading}
              onClick={() => {
                clearError();
                setStep('phone');
                setOtp('');
              }}
            >
              تغییر شماره
            </Button>
          </div>
        </div>
      )}
    </AuthLayout>
  );
}
