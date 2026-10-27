import type { ReactNode } from 'react';
import Image from 'next/image';
import { BrandMark } from '@/components/brand';
import { BackButton } from './back-button';
import styles from './auth-layout.module.css';

type AuthLayoutProps = {
  children: ReactNode;
  onBack: () => void;
};

const AUTH_IMAGE = {
  src: '/image/pages/auth.jpg',
  alt: 'ورود و عضویت شارژینو با پیامک',
  width: 1280,
  height: 720,
} as const;

export function AuthLayout({ children, onBack }: AuthLayoutProps) {
  return (
    <main className={styles.page}>
      <div className={styles.shell}>
        <div className={styles.back}>
          <BackButton onClick={onBack} />
        </div>
        <article className={styles.card}>
          <div className={styles.form}>
            <div className={styles.brand}>
              <BrandMark priority />
            </div>
            {children}
          </div>
          <div className={styles.visual}>
            <Image
              src={AUTH_IMAGE.src}
              alt={AUTH_IMAGE.alt}
              width={AUTH_IMAGE.width}
              height={AUTH_IMAGE.height}
              priority
              sizes="(max-width: 1023px) 100vw, 520px"
              className={styles.img}
            />
          </div>
        </article>
      </div>
    </main>
  );
}
