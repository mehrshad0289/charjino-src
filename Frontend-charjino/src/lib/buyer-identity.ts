export type IdentityKind = 'player' | 'user' | 'account';

const COPY: Record<
  IdentityKind,
  {
    field: string;
    check: string;
    result: string;
    hint: string;
    missing: string;
    confirm: string;
    resultCaption: string;
  }
> = {
  player: {
    field: 'شناسه بازیکن',
    check: 'بررسی بازیکن',
    result: 'بازیکن پیدا شد',
    hint: 'شناسه بازیکن معتبر است. می‌توانی پرداخت را ادامه دهی.',
    missing: 'شناسه بازیکن را وارد کن.',
    confirm: 'شناسه بازیکن را بررسی کردم و درست است',
    resultCaption: 'نام بازیکن',
  },
  user: {
    field: 'نام کاربری',
    check: 'بررسی کاربر',
    result: 'کاربر پیدا شد',
    hint: 'نام کاربری معتبر است. می‌توانی پرداخت را ادامه دهی.',
    missing: 'نام کاربری را وارد کن.',
    confirm: 'نام کاربری را بررسی کردم و درست است',
    resultCaption: 'نام کاربری',
  },
  account: {
    field: 'شناسه حساب',
    check: 'بررسی حساب',
    result: 'حساب پیدا شد',
    hint: 'شناسه حساب معتبر است. می‌توانی پرداخت را ادامه دهی.',
    missing: 'شناسه حساب را وارد کن.',
    confirm: 'شناسه حساب را بررسی کردم و درست است',
    resultCaption: 'شناسه حساب',
  },
};

export function identityKindOf(value: unknown): IdentityKind {
  if (value === 'user' || value === 'account' || value === 'player') {
    return value;
  }
  return 'player';
}

export function identityCopy(kind: unknown) {
  return COPY[identityKindOf(kind)];
}
