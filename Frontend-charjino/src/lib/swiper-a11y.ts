export function hideDuplicateSlideMedia(root?: HTMLElement | null) {
  if (!root) return;
  root.querySelectorAll('.swiper-slide-duplicate img, .swiper-slide-duplicate video').forEach((node) => {
    if (node instanceof HTMLImageElement) {
      node.alt = '';
    }
    node.setAttribute('aria-hidden', 'true');
    node.removeAttribute('aria-label');
  });
}
