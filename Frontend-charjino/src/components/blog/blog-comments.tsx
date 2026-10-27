'use client';

import { addBlogComment, likeBlogComment } from '@/actions/blog.actions';
import { ThreadComments } from '@/components/comments/thread-comments';
import { authHref } from '@/lib/safe-path';
import { ROUTES } from '@/lib/routes';
import type { BlogComment } from '@/types/blog';

type BlogCommentsProps = {
  slug: string;
  signedIn: boolean;
  initialItems: BlogComment[];
};

export function BlogComments({ slug, signedIn, initialItems }: BlogCommentsProps) {
  return (
    <ThreadComments
      signedIn={signedIn}
      initialItems={initialItems}
      loginHref={authHref(`${ROUTES.blogPost(slug)}#comments`)}
      onSubmit={(body, parentId) => addBlogComment(slug, body, parentId)}
      onLike={(id) => likeBlogComment(slug, id)}
    />
  );
}
