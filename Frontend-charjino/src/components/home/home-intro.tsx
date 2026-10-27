import Image from 'next/image';
import Link from 'next/link';

import { GAME_KEYS_SLUG, GIFT_CARD_SLUG } from '@/lib/catalog-family';
import { ROUTES } from '@/lib/routes';
import { HOME_H1 } from '@/lib/seo';

import styles from './home-intro.module.css';

const GAME_TOPUP_SLUG = 'game-topup';
const INTRO_IMAGE = '/images/home/intro-section-art.jpg';
const INTRO_ALT = 'شارژینو — شارژ بازی، گیفت کارت و کلید بازی با تومان';
const H1_REST = HOME_H1.startsWith('شارژینو') ? HOME_H1.slice('شارژینو'.length) : `؛ ${HOME_H1}`;

const shopTopup = ROUTES.category(GAME_TOPUP_SLUG);
const shopGift = ROUTES.category(GIFT_CARD_SLUG);
const shopKeys = ROUTES.category(GAME_KEYS_SLUG);

type HomeIntroProps = {
  html?: string | null;
};

export function HomeIntro({ html }: HomeIntroProps) {
  const copy = (html ?? '').trim();

  return (
    <section className={styles.page} aria-labelledby="home-intro-heading">
      <div className={styles.shell}>
        <article className={styles.intro}>
          <div className={styles.copy}>
            <h1 id="home-intro-heading" className={styles.title}>
              <span className={styles.brand}>شارژینو</span>
              {H1_REST}
            </h1>
            {copy ? (
              <div dangerouslySetInnerHTML={{ __html: copy }} />
            ) : null}
            <div className={styles.links}>
              <Link href={shopTopup} className={`${styles.chip} ${styles.chipPrimary}`}>
                خرید شارژ بازی
              </Link>
              <Link href={shopGift} className={`${styles.chip} ${styles.chipOutline}`}>
                خرید گیفت کارت
              </Link>
              <Link href={shopKeys} className={`${styles.chip} ${styles.chipOutline}`}>
                کلید بازی
              </Link>
            </div>
          </div>
          <div className={styles.visual}>
            <Image
              src={INTRO_IMAGE}
              alt={INTRO_ALT}
              width={1200}
              height={675}
              sizes="(max-width: 1023px) 100vw, 48vw"
              className={styles.img}
            />
          </div>
        </article>
      </div>
    </section>
  );
}
