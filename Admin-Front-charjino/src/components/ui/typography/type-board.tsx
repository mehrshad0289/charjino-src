import { TYPE_STYLES, WEIGHT_VALUE } from './styles';

export function TypeBoard() {
  return (
    <article className='w-full max-w-[90rem] min-w-0 text-gray-800' dir='ltr'>
      <div className='hidden grid-cols-[minmax(0,1.4fr)_4.5rem_6.5rem_6.5rem_minmax(0,1.6fr)] items-center gap-x-6 gap-y-4 border-b border-gray-200 pb-4 text-sm font-semibold text-gray-500 min-[901px]:grid'>
        <span>Style name</span>
        <span>Size</span>
        <span>Line Height</span>
        <span>Weight</span>
        <span>Style</span>
      </div>
      {TYPE_STYLES.map((style) => (
        <div
          className='grid grid-cols-3 items-center gap-x-3 gap-y-2 border-b border-gray-100 py-4 min-[901px]:grid-cols-[minmax(0,1.4fr)_4.5rem_6.5rem_6.5rem_minmax(0,1.6fr)] min-[901px]:gap-x-6 min-[901px]:gap-y-4 min-[901px]:py-5'
          key={style.name}
        >
          <span className='col-span-full text-sm leading-[22px] text-gray-700 min-[901px]:col-auto'>
            {style.name}
          </span>
          <span className='text-sm leading-[22px] text-gray-700'>
            <span className='mb-0.5 block text-[11px] font-semibold text-gray-400 min-[901px]:hidden'>Size</span>
            {style.size}
          </span>
          <span className='text-sm leading-[22px] text-gray-700'>
            <span className='mb-0.5 block text-[11px] font-semibold text-gray-400 min-[901px]:hidden'>
              Line Height
            </span>
            {style.lineHeight}
          </span>
          <span className='text-sm leading-[22px] text-gray-700'>
            <span className='mb-0.5 block text-[11px] font-semibold text-gray-400 min-[901px]:hidden'>Weight</span>
            {style.weight}
          </span>
          <p
            className='col-span-full m-0 max-w-full overflow-x-auto text-right font-outfit text-gray-900 [direction:rtl] min-[901px]:col-auto'
            style={{
              fontSize: style.size,
              lineHeight: `${style.lineHeight}px`,
              fontWeight: WEIGHT_VALUE[style.weight]
            }}
          >
            {style.sample}
          </p>
        </div>
      ))}
    </article>
  );
}
