import { Switch, type SwitchSize } from './switch';

const TITLE = 'Title text goes here';
const COPY = 'Secondary text line goes here';
const SIZES: SwitchSize[] = ['S', 'M', 'L'];
const SIZE_LABEL: Record<SwitchSize, string> = {
  S: 'Small',
  M: 'Medium',
  L: 'Large',
};

export function SwitchBoard() {
  return (
    <div className="flex min-w-0 flex-col gap-10" dir="ltr">
      <div className="grid gap-10 min-[900px]:grid-cols-3">
        {SIZES.map((size) => (
          <section key={size} className="flex min-w-0 flex-col gap-8">
            <h2 className="text-base font-semibold text-gray-900">
              {SIZE_LABEL[size]}
            </h2>
            <div className="flex items-center gap-4">
              <Switch size={size} defaultChecked />
              <Switch size={size} />
            </div>
            <Switch size={size} defaultChecked title={TITLE} />
            <Switch size={size} title={TITLE} />
            <Switch
              size={size}
              defaultChecked
              title={TITLE}
              description={COPY}
            />
            <Switch size={size} title={TITLE} description={COPY} />
          </section>
        ))}
      </div>

      <section className="border-t border-gray-100 pt-8" dir="rtl">
        <h2 className="mb-4 text-base font-semibold text-gray-900">ردیف محصول</h2>
        <Switch size="S" title="امکان دورکاری" className="w-[290px] justify-between" />
      </section>
    </div>
  );
}
