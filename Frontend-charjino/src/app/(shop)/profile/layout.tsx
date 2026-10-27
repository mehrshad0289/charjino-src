import type { Metadata } from 'next';
import { Container, WaveDivider } from '@/components/ui';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = buildMetadata({
  title: 'حساب من',
  description: PAGE_DESCRIPTIONS.profile,
  path: ROUTES.profile,
  noindex: true,
});

export default function ProfileLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <>
      <Container className="py-6 pb-16">
        <div className="mx-auto flex max-w-[960px] flex-col gap-5">{children}</div>
      </Container>
      <WaveDivider />
    </>
  );
}
