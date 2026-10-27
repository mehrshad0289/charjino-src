import { Container, WaveDivider } from '@/components/ui';

export function LeaderboardScreen() {
  return (
    <>
      <Container className="flex flex-col items-center justify-center gap-5 pt-14 pb-12 sm:pt-24">
        <div className="flex w-full max-w-xl flex-col items-center gap-4 rounded-2xl bg-paper p-8 text-center ring-1 ring-border">
          <h1 className="text-3xl font-bold text-text sm:text-4xl">جدول برترین‌ها</h1>
          <p className="text-lg font-semibold text-primary-600">به‌زودی</p>
          <p className="text-sm leading-7 text-text/60">
            این بخش هنوز به API وصل نیست. فهرست ساختگی و جایزهٔ قابل‌خرید نمایش داده نمی‌شود.
          </p>
        </div>
      </Container>
      <WaveDivider />
    </>
  );
}
