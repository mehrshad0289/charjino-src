import { ComponentCard } from '../component-card';
import { YouTubeEmbed } from './youtube-embed';

export function VideosExample() {
  return (
    <div className='grid grid-cols-1 gap-5 sm:gap-6 xl:grid-cols-2'>
      <div className='space-y-5 sm:space-y-6'>
        <ComponentCard title='نسبت ویدیو ۱۶:۹'>
          <YouTubeEmbed videoId='dQw4w9WgXcQ' />
        </ComponentCard>
        <ComponentCard title='نسبت ویدیو ۴:۳'>
          <YouTubeEmbed videoId='dQw4w9WgXcQ' aspectRatio='4:3' />
        </ComponentCard>
      </div>
      <div className='space-y-5 sm:space-y-6'>
        <ComponentCard title='نسبت ویدیو ۲۱:۹'>
          <YouTubeEmbed videoId='dQw4w9WgXcQ' aspectRatio='21:9' />
        </ComponentCard>
        <ComponentCard title='نسبت ویدیو ۱:۱'>
          <YouTubeEmbed videoId='dQw4w9WgXcQ' aspectRatio='1:1' />
        </ComponentCard>
      </div>
    </div>
  );
}
