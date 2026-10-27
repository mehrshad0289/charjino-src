import { FigmaIndex } from '@/components/figma';

import { FIGMA_PAGES } from './catalog';

export const metadata = {
  title: 'گلوبال'
};

export default function FigmaIndexPage() {
  return <FigmaIndex pages={FIGMA_PAGES} />;
}
