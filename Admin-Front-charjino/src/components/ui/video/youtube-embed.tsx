type AspectRatio = '16:9' | '4:3' | '21:9' | '1:1';

type YouTubeEmbedProps = {
  videoId: string;
  aspectRatio?: AspectRatio;
  title?: string;
  className?: string;
};

const ASPECT: Record<AspectRatio, string> = {
  '16:9': 'aspect-video',
  '4:3': 'aspect-4/3',
  '21:9': 'aspect-21/9',
  '1:1': 'aspect-square'
};

export function YouTubeEmbed({
  videoId,
  aspectRatio = '16:9',
  title = 'ویدیو',
  className = ''
}: YouTubeEmbedProps) {
  return (
    <div className={`overflow-hidden rounded-lg ${ASPECT[aspectRatio]} ${className}`}>
      <iframe
        src={`https://www.youtube.com/embed/${videoId}`}
        title={title}
        frameBorder='0'
        allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture'
        allowFullScreen
        className='h-full w-full'
      />
    </div>
  );
}
