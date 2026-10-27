import Image from 'next/image';
import Link from 'next/link';
import { CardIcon, DocumentTextIcon, HeartIcon, ProfileIcon } from '@/components/ui/icon';
import { toFaDigits } from '@/lib/fa';
import { ROUTES } from '@/lib/routes';
import { ProfileSubnav } from './profile-subnav';
import styles from './profile.module.css';

const CARDS = [
  {
    href: ROUTES.profileOrders,
    title: 'سفارش‌ها',
    text: 'لیست و جزئیات فاکتور، وضعیت، محصول، Player ID و پرداخت.',
    icon: CardIcon,
    tone: styles.orders,
  },
  {
    href: ROUTES.profileTickets,
    title: 'تیکت پشتیبانی',
    text: 'ثبت تیکت جدید و پیگیری پاسخ پشتیبانی.',
    icon: DocumentTextIcon,
    tone: styles.tickets,
  },
  {
    href: ROUTES.profileFavorites,
    title: 'علاقه‌مندی‌ها',
    text: 'بازی‌ها و گیفت‌کارت‌هایی که ذخیره کرده‌اید.',
    icon: HeartIcon,
    tone: styles.fav,
  },
  {
    href: ROUTES.profileAccount,
    title: 'اطلاعات حساب',
    text: 'شماره موبایل و ویرایش نام نمایشی.',
    icon: ProfileIcon,
    tone: styles.account,
  },
] as const;

type ProfileHubProps = {
  phoneNumber?: string | null;
  name?: string | null;
};

export function ProfileHub({ phoneNumber, name }: ProfileHubProps) {
  const phone = phoneNumber ? toFaDigits(phoneNumber) : null;

  return (
    <>
      <section className={styles.hero}>
        <div className={styles.visual}>
          <Image
            src="/image/pages/profile.jpg"
            alt="حساب کاربری شارژینو"
            fill
            priority
            sizes="(max-width: 900px) 100vw, 480px"
            className={styles.img}
          />
        </div>
        <div className={styles.body}>
          <div className={styles.eyebrow}>حساب کاربری</div>
          <h1 className={styles.title}>حساب من</h1>
          <p className={styles.lede}>
            {name?.trim()
              ? `${name.trim()}، سفارش‌ها، تیکت پشتیبانی، علاقه‌مندی‌ها و اطلاعات حساب — همه در یک جا.`
              : 'سفارش‌ها، تیکت پشتیبانی، علاقه‌مندی‌ها و اطلاعات حساب — همه در یک جا.'}
          </p>
          <div className={styles.meta}>
            {phone ? <span className={styles.chip}>{phone}</span> : null}
            <span className={styles.chip}>ورود با پیامک</span>
          </div>
        </div>
      </section>
      <ProfileSubnav />
      <div className={styles.grid}>
        {CARDS.map((card) => {
          const Icon = card.icon;
          return (
            <Link key={card.href} href={card.href} className={styles.card}>
              <span className={`${styles.icon} ${card.tone}`}>
                <Icon size={20} />
              </span>
              <div>
                <h2>{card.title}</h2>
                <p>{card.text}</p>
              </div>
            </Link>
          );
        })}
      </div>
    </>
  );
}
