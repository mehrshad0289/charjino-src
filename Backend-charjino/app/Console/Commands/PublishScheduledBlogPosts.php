<?php

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Modules\Blog\BlogRevalidator;
use Illuminate\Console\Command;

class PublishScheduledBlogPosts extends Command
{
    protected $signature = 'charjino:publish-scheduled-posts';

    protected $description = 'Publish scheduled Charjino blog posts whose published_at has passed';

    public function handle(BlogRevalidator $revalidator): int
    {
        $posts = BlogPost::query()
            ->with(['category', 'tags', 'author'])
            ->where('status', BlogPost::STATUS_SCHEDULED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->get();
        foreach ($posts as $post) {
            $post->status = BlogPost::STATUS_PUBLISHED;
            $post->save();
            $revalidator->pingPost($post);
        }
        $this->info('published '.$posts->count());

        return self::SUCCESS;
    }
}
