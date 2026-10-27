import { FigmaFrame } from '@/components/figma';
import { TypeBoard } from '@/components/ui';

export const metadata = {
  title: 'Typography'
};

export default function FigmaTypographyPage() {
  return (
    <FigmaFrame title='Typography' frame='Type'>
      <TypeBoard />
    </FigmaFrame>
  );
}
