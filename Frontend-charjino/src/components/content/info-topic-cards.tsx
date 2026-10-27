import Link from 'next/link';
import { INFO_TOPIC_CARDS } from '@/data/info-pages';

import styles from './info-page.module.css';

type InfoTopicCardsProps = {
  currentHref: string;
};

export function InfoTopicCards({ currentHref }: InfoTopicCardsProps) {
  return (
    <nav className={styles.topics} aria-label="صفحات راهنما">
      {INFO_TOPIC_CARDS.map((item) => {
        const current = item.href === currentHref;
        const className = `${styles.topic} ${current ? styles.topicCurrent : ''}`;
        const inner = (
          <>
            <p className={styles.topicTitle}>{item.title}</p>
            <p className={styles.topicText}>{item.text}</p>
          </>
        );

        if (current) {
          return (
            <div key={item.href} className={className} aria-current="page">
              {inner}
            </div>
          );
        }

        return (
          <Link key={item.href} href={item.href} className={className}>
            {inner}
          </Link>
        );
      })}
    </nav>
  );
}
