'use client';

import { useEffect, useRef, useState, type CSSProperties, type ReactNode } from 'react';
import styles from './home-marquee.module.css';

const PX_PER_SEC = 38;
const DRAG_CLICK_PX = 8;

type HomeMarqueeProps = {
  children: ReactNode;
  gap?: number;
  minHeight?: string;
};

export function HomeMarquee({ children, gap = 12, minHeight }: HomeMarqueeProps) {
  const firstRef = useRef<HTMLDivElement>(null);
  const dupRef = useRef<HTMLDivElement>(null);
  const dragLayerRef = useRef<HTMLDivElement>(null);
  const offsetRef = useRef(0);
  const skipClickRef = useRef(false);
  const reducedRef = useRef(false);
  const dragRef = useRef<{ id: number; x: number; start: number; moved: boolean } | null>(null);
  const [loop, setLoop] = useState(false);
  const [paused, setPaused] = useState(false);
  const [duration, setDuration] = useState(24);

  useEffect(() => {
    setLoop(true);
  }, []);

  useEffect(() => {
    const query = window.matchMedia('(prefers-reduced-motion: reduce)');
    const sync = () => {
      reducedRef.current = query.matches;
    };
    sync();
    query.addEventListener('change', sync);
    return () => query.removeEventListener('change', sync);
  }, []);

  useEffect(() => {
    const el = firstRef.current;
    if (!el) return undefined;

    const update = () => {
      const width = el.scrollWidth;
      if (width > 1) {
        setDuration(Math.max(8, width / PX_PER_SEC));
      }
    };

    update();
    const observer = new ResizeObserver(update);
    observer.observe(el);
    return () => observer.disconnect();
  }, [children, loop]);

  useEffect(() => {
    const root = dupRef.current;
    if (!root) return;
    root.querySelectorAll('a, button, input, select, textarea').forEach((node) => {
      (node as HTMLElement).tabIndex = -1;
    });
  }, [children, loop]);

  function loopWidth() {
    return firstRef.current?.scrollWidth ?? 0;
  }

  function wrap(value: number) {
    const width = loopWidth();
    if (width <= 1) return value;
    return ((value % width) + width) % width;
  }

  function applyOffset(value: number) {
    const next = wrap(value);
    offsetRef.current = next;
    const layer = dragLayerRef.current;
    if (layer) {
      layer.style.transform = next ? `translate3d(${next}px,0,0)` : '';
    }
  }

  function endDrag() {
    if (dragRef.current) {
      applyOffset(offsetRef.current);
      dragRef.current = null;
    }
    setPaused(false);
  }

  const vars = {
    '--marquee-duration': `${duration}s`,
    '--marquee-gap': `${gap}px`,
    ...(minHeight ? { '--marquee-min-height': minHeight } : {}),
  } as CSSProperties;

  return (
    <div
      className={styles.viewport}
      style={vars}
      onPointerDown={(event) => {
        if (reducedRef.current) return;
        if (event.pointerType === 'mouse' && event.button !== 0) return;
        skipClickRef.current = false;
        setPaused(true);
        dragRef.current = {
          id: event.pointerId,
          x: event.clientX,
          start: offsetRef.current,
          moved: false,
        };
        event.currentTarget.setPointerCapture(event.pointerId);
      }}
      onPointerMove={(event) => {
        const drag = dragRef.current;
        if (!drag || drag.id !== event.pointerId) return;
        const delta = event.clientX - drag.x;
        if (Math.abs(delta) > DRAG_CLICK_PX) {
          drag.moved = true;
          skipClickRef.current = true;
        }
        applyOffset(drag.start + delta);
      }}
      onPointerUp={endDrag}
      onPointerCancel={endDrag}
      onClickCapture={(event) => {
        if (!skipClickRef.current) return;
        event.preventDefault();
        event.stopPropagation();
        skipClickRef.current = false;
      }}
    >
      <div ref={dragLayerRef} className={styles.drag}>
        <div
          className={[
            styles.track,
            loop ? styles.running : '',
            paused ? styles.paused : '',
          ]
            .filter(Boolean)
            .join(' ')}
        >
          <div ref={firstRef} className={styles.set}>
            {children}
          </div>
          {loop ? (
            <div ref={dupRef} className={`${styles.set} ${styles.dup}`} aria-hidden="true" inert>
              {children}
            </div>
          ) : null}
        </div>
      </div>
    </div>
  );
}
