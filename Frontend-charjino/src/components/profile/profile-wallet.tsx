import { formatFaDateTime } from '@/lib/fa';
import { formatTomanNumber } from '@/lib/price';
import { ProfileHeading } from './profile-chrome';
import { ProfileSubnav } from './profile-subnav';
import styles from './profile.module.css';

type WalletPayload = {
  balanceToman: number;
  note: string;
  transactions: Array<{
    type: string;
    amountToman: number;
    balanceAfter: number;
    reason: string;
    createdAt?: string | null;
  }>;
};

export function ProfileWallet({ wallet }: { wallet: WalletPayload }) {
  return (
    <>
      <ProfileSubnav />
      <ProfileHeading title="کیف پول شارژینو" />
      <div className={styles.panel}>
        <p className="text-sm text-text/60">موجودی</p>
        <p className="mt-1 text-2xl font-bold text-text">{formatTomanNumber(wallet.balanceToman)} تومان</p>
        <p className="mt-3 text-sm leading-7 text-text/70">{wallet.note}</p>
      </div>
      <div className={styles.panel}>
        <h2 className="mb-4 text-sm font-semibold text-text">تراکنش‌ها</h2>
        {wallet.transactions.length === 0 ? (
          <p className="text-sm text-text/50">تراکنشی ثبت نشده است.</p>
        ) : (
          <ul className="space-y-3 text-sm">
            {wallet.transactions.map((row, index) => (
              <li key={`${row.createdAt}-${index}`} className="flex justify-between gap-3">
                <span className="text-text/80">
                  {row.type === 'credit' ? 'افزایش' : 'کاهش'} · {formatTomanNumber(row.amountToman)} تومان
                </span>
                <span className="text-xs text-text/45">{formatFaDateTime(row.createdAt)}</span>
              </li>
            ))}
          </ul>
        )}
      </div>
    </>
  );
}
