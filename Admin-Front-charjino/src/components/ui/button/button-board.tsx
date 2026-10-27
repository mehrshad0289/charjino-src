import { BoxIcon } from '../icon';
import { Button, type ButtonSize, type ButtonVariant } from './button-root';

const ROW: { variant: ButtonVariant; size: ButtonSize; label: string }[] = [
  { variant: 'primary', size: 'sm', label: 'primary sm' },
  { variant: 'primary', size: 'md', label: 'primary md' },
  { variant: 'outline', size: 'sm', label: 'outline sm' },
  { variant: 'outline', size: 'md', label: 'outline md' }
];

export function ButtonBoard() {
  return (
    <div className='flex min-w-0 flex-col gap-[clamp(24px,4vw,40px)]'>
      <section className='min-w-0'>
        <h2 className='mb-4 text-[clamp(16px,2.5vw,18px)] font-semibold text-gray-900'>Default</h2>
        <div className='flex flex-wrap items-center gap-5'>
          {ROW.map((item) => (
            <Button key={`${item.variant}-${item.size}`} variant={item.variant} size={item.size}>
              Button Text
            </Button>
          ))}
        </div>
      </section>

      <section className='min-w-0'>
        <h2 className='mb-4 text-[clamp(16px,2.5vw,18px)] font-semibold text-gray-900'>Leading icon</h2>
        <div className='flex flex-wrap items-center gap-5'>
          {ROW.map((item) => (
            <Button
              key={`start-${item.variant}-${item.size}`}
              variant={item.variant}
              size={item.size}
              startIcon={<BoxIcon />}
            >
              Button Text
            </Button>
          ))}
        </div>
      </section>

      <section className='min-w-0'>
        <h2 className='mb-4 text-[clamp(16px,2.5vw,18px)] font-semibold text-gray-900'>Trailing icon</h2>
        <div className='flex flex-wrap items-center gap-5'>
          {ROW.map((item) => (
            <Button
              key={`end-${item.variant}-${item.size}`}
              variant={item.variant}
              size={item.size}
              endIcon={<BoxIcon />}
            >
              Button Text
            </Button>
          ))}
        </div>
      </section>

      <section className='min-w-0'>
        <h2 className='mb-4 text-[clamp(16px,2.5vw,18px)] font-semibold text-gray-900'>Disabled</h2>
        <div className='flex flex-wrap items-center gap-5'>
          {ROW.map((item) => (
            <Button key={`disabled-${item.variant}-${item.size}`} variant={item.variant} size={item.size} disabled>
              Button Text
            </Button>
          ))}
        </div>
      </section>
    </div>
  );
}
