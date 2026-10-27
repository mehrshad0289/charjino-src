'use client';

import { useState } from 'react';
import Link from 'next/link';
import { likeBlogPost } from '@/actions/blog.actions';
import { Button } from '@/components/ui';
import { HeartIcon } from '@/components/ui/icon';
import { toFaDigits } from '@/lib/fa';
import { authHref } from '@/lib/safe-path';
import { ROUTES } from '@/lib/routes';
import type { BlogPost } from '@/types/blog';

type BlogLikeBarProps = {
  post: BlogPost;
  signedIn: boolean;
};

export function BlogLikeBar({ post, signedIn }: BlogLikeBarProps) {
  const [liked, setLiked] = useState(Boolean(post.liked));
  const [likes, setLikes] = useState(post.likesCount);
  const [message, setMessage] = useState<string | null>(null);

  async function toggle() {
    if (!signedIn) {
      setMessage('برای لایک وارد حساب شو.');
      return;
    }
    const result = await likeBlogPost(post.slug);
    if (!result.ok) {
      setMessage(result.message);
      return;
    }
    setLiked(result.data.liked);
    setLikes(result.data.likesCount);
  }

  return (
    <div className="flex flex-wrap items-center gap-3">
      <Button type="button" variant="outline" size="M" onClick={() => void toggle()} icon={<HeartIcon size={18} />}>
        {liked ? 'پسندیدی' : 'پسندیدن'} · {toFaDigits(likes)}
      </Button>
      {!signedIn ? (
        <Link href={authHref(ROUTES.blogPost(post.slug))} className="text-sm text-primary-600">
          ورود برای لایک و نظر
        </Link>
      ) : null}
      {message ? <p className="text-sm text-text/70">{message}</p> : null}
    </div>
  );
}
