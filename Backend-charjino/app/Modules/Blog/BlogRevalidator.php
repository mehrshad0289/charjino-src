<?php

namespace App\Modules\Blog;

use App\Models\BlogPost;
use App\Modules\BuyGuide\StorefrontRevalidator;

final class BlogRevalidator
{
    public function __construct(private readonly StorefrontRevalidator $storefront) {}

    public function pingPost(?BlogPost $post): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        $tags = ['blog', 'sitemap'];
        if ($post?->slug) {
            $tags[] = 'blog:post:'.$post->slug;
            if ($post->category?->slug) {
                $tags[] = 'blog:category:'.$post->category->slug;
            }
            if ($post->relationLoaded('tags')) {
                foreach ($post->tags as $tag) {
                    $tags[] = 'blog:tag:'.$tag->slug;
                }
            }
            if ($post->author?->blog_slug) {
                $tags[] = 'blog:author:'.$post->author->blog_slug;
            }
        }
        $this->dispatchPing(array_values(array_unique($tags)));
    }

    /**
     * @param  list<string>  $extra
     */
    public function ping(array $extra = []): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        $this->dispatchPing(array_values(array_unique(array_merge(['blog', 'sitemap'], $extra))));
    }

    /**
     * @param  list<string>  $tags
     */
    private function dispatchPing(array $tags): void
    {
        if ($tags === []) {
            return;
        }
        dispatch(function () use ($tags): void {
            $this->storefront->ping($tags);
        })->afterResponse();
    }
}
