import { Badge, type BadgeColor, type BadgeVariant } from './badge';

const COLORS: BadgeColor[] = ['primary', 'success', 'error', 'warning', 'info', 'light', 'dark'];
const VARIANTS: BadgeVariant[] = ['light', 'solid'];

export function BadgeBoard() {
  return (
    <div className='flex min-w-0 flex-col gap-10'>
      {VARIANTS.map((variant) => (
        <section key={variant} className='min-w-0'>
          <h2 className='mb-4 text-base font-semibold text-gray-900'>{variant}</h2>
          <div className='flex flex-wrap items-center gap-3'>
            {COLORS.map((color) => (
              <Badge key={`${variant}-${color}`} variant={variant} color={color}>
                {color}
              </Badge>
            ))}
          </div>
        </section>
      ))}
    </div>
  );
}
