export function lockPageScroll() {
  const html = document.documentElement;
  const body = document.body;
  const gap = Math.max(0, window.innerWidth - html.clientWidth);
  const onLeft = gap > 0 && html.getBoundingClientRect().left >= gap / 2;

  const previousOverflow = {
    html: html.style.overflow,
    body: body.style.overflow,
  };

  html.style.setProperty('--scroll-lock-left', onLeft ? `${gap}px` : '0px');
  html.style.setProperty('--scroll-lock-right', onLeft ? '0px' : `${gap}px`);
  html.style.overflow = 'hidden';
  body.style.overflow = 'hidden';

  return () => {
    html.style.setProperty('--scroll-lock-left', '0px');
    html.style.setProperty('--scroll-lock-right', '0px');
    html.style.overflow = previousOverflow.html;
    body.style.overflow = previousOverflow.body;
  };
}
