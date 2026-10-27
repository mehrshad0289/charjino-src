import { Error404Screen } from '@/components/error';
import { ERROR_404_COPY } from '@/data/error';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: ERROR_404_COPY.title
};

export default function Error404Page() {
  return <Error404Screen />;
}
