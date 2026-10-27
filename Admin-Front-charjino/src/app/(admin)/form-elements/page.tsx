import { FormElementsScreen } from '@/components/form-elements';
import { FORM_ELEMENTS_COPY } from '@/data/form-elements';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: FORM_ELEMENTS_COPY.pageTitle
};

export default function FormElementsPage() {
  return <FormElementsScreen />;
}
