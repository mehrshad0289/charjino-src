import { FigmaFrame } from '@/components/figma';
import { InputBoard } from '@/components/ui';

export const metadata = {
  title: 'Inputs'
};

export default function FigmaInputsPage() {
  return (
    <FigmaFrame title='Inputs' frame='Form'>
      <InputBoard />
    </FigmaFrame>
  );
}
