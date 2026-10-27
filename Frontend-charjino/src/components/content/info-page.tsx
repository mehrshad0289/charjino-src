import type { ReactNode } from 'react';
import Image from 'next/image';
import { Container, WaveDivider } from '@/components/ui';
import { infoSlidesFor } from '@/data/info-pages';
import { InfoHeroSlider } from './info-hero-slider';
import { InfoTopicCards } from './info-topic-cards';

import styles from './info-page.module.css';

type InfoPageProps = {
  title: string;
  lede: string;
  image: string;
  imageAlt: string;
  topicHref?: string;
  children: ReactNode;
};

const INFO_IMAGE_SIZE = { width: 1280, height: 720 } as const;

export function InfoPage({ title, lede, image, imageAlt, topicHref, children }: InfoPageProps) {
  const slides = topicHref ? infoSlidesFor(topicHref) : [];
  const useSlider = slides.length > 1;

  return (
    <>
      <Container className={styles.page}>
        <article className={styles.card}>
          {useSlider ? (
            <InfoHeroSlider slides={slides} />
          ) : (
            <div className={styles.visual}>
              <Image
                src={image}
                alt={imageAlt}
                width={INFO_IMAGE_SIZE.width}
                height={INFO_IMAGE_SIZE.height}
                priority
                unoptimized={image.endsWith('.svg')}
                sizes="(max-width: 768px) 100vw, 760px"
                className={styles.img}
              />
            </div>
          )}
          {topicHref ? <InfoTopicCards currentHref={topicHref} /> : null}
          <div className={styles.copy}>
            <h1 className={styles.title}>{title}</h1>
            <p className={styles.lede}>{lede}</p>
            <div className={styles.body}>{children}</div>
          </div>
        </article>
      </Container>
      <WaveDivider />
    </>
  );
}
