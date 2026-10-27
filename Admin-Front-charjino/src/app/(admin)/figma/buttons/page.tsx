import { FigmaFrame } from '@/components/figma';
import { ButtonBoard } from '@/components/ui';

export const metadata = {
  title: 'Buttons'
};

export default function FigmaButtonsPage() {
  return (
    <FigmaFrame title='Buttons' frame='Button'>
      <ButtonBoard />
    </FigmaFrame>
  );
}
