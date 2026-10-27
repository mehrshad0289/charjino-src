import Image from 'next/image';

export function ResponsiveImage() {
  return (
    <div className='relative'>
      <div className='overflow-hidden'>
        <Image
          src='/images/grid-image/image-01.png'
          alt='تصویر پوشش'
          className='w-full rounded-xl border border-gray-200 dark:border-gray-800'
          width={1054}
          height={600}
        />
      </div>
    </div>
  );
}

export function TwoColumnImageGrid() {
  return (
    <div className='grid grid-cols-1 gap-5 sm:grid-cols-2'>
      <div>
        <Image
          src='/images/grid-image/image-02.png'
          alt='تصویر شبکه'
          className='w-full rounded-xl border border-gray-200 dark:border-gray-800'
          width={517}
          height={295}
        />
      </div>
      <div>
        <Image
          src='/images/grid-image/image-03.png'
          alt='تصویر شبکه'
          className='w-full rounded-xl border border-gray-200 dark:border-gray-800'
          width={517}
          height={295}
        />
      </div>
    </div>
  );
}

export function ThreeColumnImageGrid() {
  return (
    <div className='grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3'>
      <div>
        <Image
          src='/images/grid-image/image-04.png'
          alt='تصویر شبکه'
          className='w-full rounded-xl border border-gray-200 dark:border-gray-800'
          width={338}
          height={192}
        />
      </div>
      <div>
        <Image
          src='/images/grid-image/image-05.png'
          alt='تصویر شبکه'
          className='w-full rounded-xl border border-gray-200 dark:border-gray-800'
          width={338}
          height={192}
        />
      </div>
      <div>
        <Image
          src='/images/grid-image/image-06.png'
          alt='تصویر شبکه'
          className='w-full rounded-xl border border-gray-200 dark:border-gray-800'
          width={338}
          height={192}
        />
      </div>
    </div>
  );
}
