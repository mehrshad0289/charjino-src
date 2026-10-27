import { FigmaFrame } from '@/components/figma';
import { ColorBoard } from '@/components/ui';

export const metadata = {
  title: 'Primary colors'
};

export default function FigmaColorsPage() {
  return (
    <FigmaFrame title='Primary colors' frame='Theme'>
      <ColorBoard />
    </FigmaFrame>
  );
}
