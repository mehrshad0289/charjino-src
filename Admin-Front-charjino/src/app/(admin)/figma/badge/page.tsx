import { FigmaFrame } from '@/components/figma';
import { BadgeBoard } from '@/components/ui';

export const metadata = {
  title: 'Badge'
};

export default function FigmaBadgePage() {
  return (
    <FigmaFrame title='Badge' frame='Badge'>
      <BadgeBoard />
    </FigmaFrame>
  );
}
