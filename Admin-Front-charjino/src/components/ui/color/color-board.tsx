import { PRIMITIVE_COLORS } from './primitives';
import { COLOR_SCALES, COLOR_STEPS, type ColorStep } from './scales';

function Swatch({ step, hex }: { step: ColorStep; hex: string }) {
  return (
    <div className='flex min-w-0 flex-col items-center gap-2'>
      <span className='whitespace-nowrap text-[11px] leading-[1.2] text-gray-500'>{step}</span>
      <span
        className='aspect-square w-[clamp(36px,10vw,56px)] shrink-0 rounded-full border border-gray-200'
        style={{ backgroundColor: hex }}
      />
      <span className='whitespace-nowrap text-[11px] leading-[1.2] text-gray-500'>{hex}</span>
    </div>
  );
}

export function ColorBoard() {
  return (
    <article className='w-full min-w-0 text-gray-700' dir='ltr'>
      <header className='mb-[clamp(24px,5vw,40px)] max-w-[45rem]'>
        <h1 className='mb-4 text-[clamp(1.5rem,4vw,2rem)] font-semibold leading-[1.3] text-gray-900'>
          Primary colors
        </h1>
        <p className='mb-3 text-theme-sm leading-[20px] text-gray-500'>
          Primary colors are chosen carefully to ensure that they work well together and create a cohesive visual
          identity. These colors should also have good contrast with each other to make the design easy to read and
          navigate.
        </p>
        <p className='mb-3 text-theme-sm leading-[20px] text-gray-500'>
          In summary, primary colors are the foundational colors in a UI design system and are used for the main
          elements of a user interface. The number of primary colors used and their specific selection will depend on
          the design&apos;s goals and aesthetic.
        </p>
      </header>

      <section className='grid grid-cols-1 items-start gap-4 border-t border-gray-100 py-[clamp(16px,3vw,28px)] min-[900px]:grid-cols-[minmax(12rem,14rem)_minmax(0,1fr)] min-[900px]:gap-x-8 min-[900px]:gap-y-6'>
        <div className='min-w-0'>
          <h2 className='mb-2 text-[clamp(16px,2.4vw,18px)] font-semibold text-gray-900'>Surfaces</h2>
          <p className='text-[13px] leading-5 text-gray-500'>
            White, black, gray-dark and theme accents from TailAdmin @theme.
          </p>
        </div>
        <div className='grid min-w-0 grid-cols-[repeat(auto-fill,minmax(6rem,1fr))] gap-x-2 gap-y-3'>
          {PRIMITIVE_COLORS.map((color) => (
            <div className='flex min-w-0 flex-col items-center gap-2' key={color.token}>
              <span className='whitespace-nowrap text-[11px] leading-[1.2] text-gray-500'>{color.name}</span>
              <span
                className='aspect-square w-[clamp(36px,10vw,56px)] shrink-0 rounded-full border border-gray-200'
                style={{ backgroundColor: color.hex }}
              />
              <span className='whitespace-nowrap text-[11px] leading-[1.2] text-gray-500'>{color.hex}</span>
              <span className='text-center text-[11px] leading-[1.2] text-gray-400'>{color.token}</span>
            </div>
          ))}
        </div>
      </section>

      {COLOR_SCALES.map((scale) => (
        <section
          className='grid grid-cols-1 items-start gap-4 border-t border-gray-100 py-[clamp(16px,3vw,28px)] min-[900px]:grid-cols-[minmax(12rem,14rem)_minmax(0,1fr)] min-[900px]:gap-x-8 min-[900px]:gap-y-6'
          key={scale.token}
        >
          <div className='min-w-0'>
            <h2 className='mb-2 text-[clamp(16px,2.4vw,18px)] font-semibold text-gray-900'>{scale.name}</h2>
            <p className='text-[13px] leading-5 text-gray-500'>{scale.description}</p>
          </div>
          <div className='grid min-w-0 grid-cols-[repeat(auto-fill,minmax(4.5rem,1fr))] gap-x-2 gap-y-3'>
            {COLOR_STEPS.map((step) => (
              <Swatch key={step} step={step} hex={scale.shades[step]} />
            ))}
          </div>
        </section>
      ))}
    </article>
  );
}
