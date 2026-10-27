import type { ReactNode } from 'react';

import styles from './info-page.module.css';

type InfoPointProps = {
  title: string;
  text: string;
  children?: ReactNode;
};

export function InfoPoint({ title, text, children }: InfoPointProps) {
  return (
    <section className={styles.point}>
      <h2 className={styles.pointTitle}>{title}</h2>
      <p className={styles.pointText}>{text}</p>
      {children}
    </section>
  );
}
