import { CallIcon } from '@/components/ui';
import { SUPPORT_PHONE_LOCAL, SUPPORT_PHONE_TEL } from '@/lib/contact';
import { toFaDigits } from '@/lib/fa';

export function PhoneContactCard() {
  return (
    <a
      href={`tel:${SUPPORT_PHONE_TEL}`}
      className="flex items-center gap-3 rounded-2xl bg-card px-4 py-3 ring-1 ring-border transition-colors hover:ring-primary-600"
    >
      <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white">
        <CallIcon size={20} />
      </span>
      <span className="min-w-0">
        <span className="block text-sm font-semibold text-text">تلفن</span>
        <span className="block text-sm font-bold text-text" dir="ltr">
          {toFaDigits(SUPPORT_PHONE_LOCAL)}
        </span>
        <span className="block text-xs text-text/60" dir="ltr">
          {SUPPORT_PHONE_LOCAL}
        </span>
      </span>
    </a>
  );
}
