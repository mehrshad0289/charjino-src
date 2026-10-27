'use client';

import { useState } from 'react';
import type { BuyGuideVideo } from '@/types/buy-guide';

function embedSrc(video: BuyGuideVideo): string | null {
  const url = video.url?.trim() ?? '';
  if (video.provider === 'youtube') {
    const id = url.match(/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{6,})/)?.[1];
    return id ? `https://www.youtube-nocookie.com/embed/${id}` : null;
  }
  if (video.provider === 'aparat') {
    const id = url.match(/aparat\.com\/v\/([A-Za-z0-9_-]+)/i)?.[1];
    return id ? `https://www.aparat.com/video/video/embed/videohash/${id}/vt/frame` : null;
  }
  return null;
}

export function VideoFacade({ video }: { video: BuyGuideVideo }) {
  const [play, setPlay] = useState(false);
  const poster = video.thumbnail?.src;
  const embed = embedSrc(video);
  const selfSrc = video.video?.src;

  if (play && embed) {
    return (
      <iframe
        title={video.title || 'ویدیو آموزشی'}
        src={embed}
        className="aspect-video w-full rounded-xl border-0"
        allow="accelerometer; autoplay; encrypted-media; picture-in-picture"
        allowFullScreen
      />
    );
  }
  if (play && selfSrc) {
    return (
      <video className="aspect-video w-full rounded-xl" controls preload="none" poster={poster} src={selfSrc} />
    );
  }

  return (
    <button
      type="button"
      onClick={() => setPlay(true)}
      className="relative block w-full overflow-hidden rounded-xl bg-card-muted text-start"
    >
      {poster ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={poster}
          alt={video.thumbnail?.alt || video.title || 'پیش‌نمایش ویدیو'}
          width={1280}
          height={720}
          className="aspect-video h-auto w-full object-cover"
        />
      ) : (
        <div className="aspect-video bg-card-muted" />
      )}
      <span className="absolute inset-0 flex items-center justify-center bg-black/35 text-sm text-white">پخش ویدیو</span>
      {video.title ? <span className="block px-3 py-2 text-sm text-text">{video.title}</span> : null}
    </button>
  );
}
