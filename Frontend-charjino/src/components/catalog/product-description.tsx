'use client';

import { useEffect, useId, useRef, useState, type CSSProperties } from 'react';
import { ChevronIcon } from '@/components/ui';
import styles from './product-description.module.css';

type ProductDescriptionProps = {
  html?: string | null;
  text?: string | null;
};

const LINES = 3;

export function ProductDescription({ html, text }: ProductDescriptionProps) {
  const markup = html?.trim() ?? '';
  const plain = text?.trim() ?? '';
  const content = markup || plain;
  const isHtml = Boolean(markup);
  const [open, setOpen] = useState(false);
  const [overflows, setOverflows] = useState(false);
  const [measured, setMeasured] = useState(false);
  const [fullHeight, setFullHeight] = useState(0);
  const innerRef = useRef<HTMLElement>(null);
  const panelId = useId();

  useEffect(() => {
    const node = innerRef.current;
    if (!node) return;

    const measure = () => {
      const line = Number.parseFloat(getComputedStyle(node).lineHeight) || 28;
      setFullHeight(node.scrollHeight);
      setOverflows(node.scrollHeight > line * LINES + 2);
      setMeasured(true);
    };

    measure();
    const observer = new ResizeObserver(measure);
    observer.observe(node);
    return () => observer.disconnect();
  }, [content]);

  if (!content) return null;

  const shouldClamp = !measured || overflows;
  const clipClass = [
    styles.clip,
    shouldClamp ? styles.clamp : '',
    open ? styles.open : '',
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <div className={open ? `${styles.root} ${styles.open}` : styles.root}>
      <div
        className={clipClass}
        style={fullHeight ? ({ '--desc-full': `${fullHeight}px` } as CSSProperties) : undefined}
      >
        <div className={styles.panel} id={panelId}>
          {isHtml ? (
            <div
              ref={(node) => {
                innerRef.current = node;
              }}
              className={`${styles.body} prose-guide`}
              dangerouslySetInnerHTML={{ __html: markup }}
            />
          ) : (
            <p
              ref={(node) => {
                innerRef.current = node;
              }}
              className={styles.body}
            >
              {plain}
            </p>
          )}
        </div>
        <div className={styles.fade} aria-hidden />
      </div>
      {overflows ? (
        <button
          type="button"
          className={styles.toggle}
          aria-expanded={open}
          aria-controls={panelId}
          onClick={() => setOpen((value) => !value)}
        >
          {open ? 'بستن' : 'ادامه مطلب'}
          <span className={styles.chev}>
            <ChevronIcon size={16} />
          </span>
        </button>
      ) : null}
    </div>
  );
}
