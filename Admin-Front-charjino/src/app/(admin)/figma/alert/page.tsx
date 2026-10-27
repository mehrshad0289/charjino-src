import { FigmaFrame } from '@/components/figma';
import { AlertBoard } from '@/components/ui';

export const metadata = {
  title: 'Alert'
};

export default function FigmaAlertPage() {
  return (
    <FigmaFrame title='Alert' frame='Alert'>
      <AlertBoard />
    </FigmaFrame>
  );
}
