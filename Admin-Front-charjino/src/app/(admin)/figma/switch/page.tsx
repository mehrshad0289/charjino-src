import { FigmaFrame } from '@/components/figma';
import { SwitchBoard } from '@/components/ui';

export const metadata = {
  title: 'Switch'
};

export default function FigmaSwitchPage() {
  return (
    <FigmaFrame title='Switch' frame='Toggle'>
      <SwitchBoard />
    </FigmaFrame>
  );
}
