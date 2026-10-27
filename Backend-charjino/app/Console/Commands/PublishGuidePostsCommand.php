<?php

namespace App\Console\Commands;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogSlugRedirect;
use App\Models\Product;
use App\Models\User;
use App\Modules\Blog\BlogImageService;
use App\Modules\Blog\BlogService;
use App\Modules\Blog\GuideArticles;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\ImageManager;

class PublishGuidePostsCommand extends Command
{
    protected $signature = 'charjino:publish-guide-posts {--force : Replace cover even if the post already has one}';

    protected $description = 'Upsert and publish the first Charjino shop guide articles';

    public function handle(BlogService $blog, BlogImageService $images): int
    {
        $author = User::query()->where('role', 'admin')->orderBy('id')->first();
        if (! $author) {
            $this->error('admin user missing');

            return self::FAILURE;
        }
        $categoryId = $this->categoryId($blog);
        $ok = 0;
        foreach (GuideArticles::all() as $article) {
            $slug = (string) $article['slug'];
            $legacy = array_values(array_filter(array_map('strval', $article['legacySlugs'] ?? [])));
            $existing = BlogPost::query()
                ->where('slug', $slug)
                ->when($legacy !== [], fn ($query) => $query->orWhereIn('slug', $legacy))
                ->first();
            $cover = $this->cover($images, $article, $existing);
            $payload = [
                'title' => $article['title'],
                'slug' => $slug,
                'excerpt' => $article['excerpt'],
                'content' => $article['content'],
                'categoryId' => $categoryId,
                'userId' => $author->id,
                'metaTitle' => $article['metaTitle'],
                'metaDescription' => $article['metaDescription'],
                'focusKeyword' => $article['focusKeyword'],
                'faq' => $article['faq'],
                'cover' => $cover['url'],
                'coverAlt' => $article['coverAlt'],
                'coverWidth' => $cover['width'],
                'coverHeight' => $cover['height'],
                'noindex' => false,
                'productIds' => $this->productIds($article),
                'publish' => true,
                'status' => BlogPost::STATUS_PUBLISHED,
                'publishedAt' => $article['publishedAt'],
            ];
            if ($existing) {
                $blog->updatePost($existing, $payload);
            } else {
                $blog->createPost($payload, (string) $author->id);
            }
            foreach ($legacy as $oldSlug) {
                if ($oldSlug !== '' && $oldSlug !== $slug) {
                    BlogSlugRedirect::remember(BlogSlugRedirect::TYPE_POST, $oldSlug, $slug);
                }
            }
            $this->line($slug);
            $ok++;
        }
        $this->info('published '.$ok);

        return self::SUCCESS;
    }

    private function categoryId(BlogService $blog): int
    {
        $existing = BlogCategory::query()->where('slug', 'guides')->first();
        if ($existing) {
            return (int) $existing->id;
        }
        $created = $blog->createCategory([
            'title' => 'آموزش خرید',
            'slug' => 'guides',
            'description' => 'راهنمای خرید شارژ بازی، گیفت استیم و پرمیوم تلگرام با تومان در شارژینو؛ بدون رمز اکانت.',
            'metaTitle' => 'آموزش خرید شارژ بازی',
            'metaDescription' => 'آموزش خرید یوسی، سی‌پی، الماس، گیفت استیم و پرمیوم تلگرام با تومان در شارژینو؛ بدون رمز اکانت و از مسیر خود فروشگاه.',
            'isActive' => true,
            'sortOrder' => 10,
        ]);

        return (int) $created['id'];
    }

    /**
     * @param  array<string, mixed>  $article
     * @return list<int>
     */
    private function productIds(array $article): array
    {
        $ids = [];
        foreach ($article['productHandles'] ?? [] as $handle) {
            $id = Product::query()->where('handle', (string) $handle)->value('id');
            if ($id) {
                $ids[] = (int) $id;
            }
        }
        foreach ($article['searchTitles'] ?? [] as $title) {
            $id = Product::query()
                ->where('visible', true)
                ->where('title', 'like', '%'.$title.'%')
                ->value('id');
            if ($id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $article
     * @return array{url: string, width: int, height: int}
     */
    private function cover(BlogImageService $images, array $article, ?BlogPost $existing): array
    {
        if ($existing?->cover && $existing->cover_width && $existing->cover_height && ! $this->option('force')) {
            return [
                'url' => (string) $existing->cover,
                'width' => (int) $existing->cover_width,
                'height' => (int) $existing->cover_height,
            ];
        }
        if (app()->runningUnitTests()) {
            return [
                'url' => '/storage/blog/guides/'.(string) $article['slug'].'.webp',
                'width' => BlogImageService::COVER_MIN_WIDTH,
                'height' => BlogImageService::COVER_MIN_HEIGHT,
            ];
        }
        $manager = new ImageManager(new Driver);
        $image = $manager->create(1200, 630)->fill('#171717');
        $accent = (string) ($article['accent'] ?? '#C9A227');
        $image->drawRectangle(0, 560, function (RectangleFactory $rectangle) use ($accent): void {
            $rectangle->size(1200, 70);
            $rectangle->background($accent);
        });
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'charjino-guide-'.(string) $article['slug'].'.png';
        $image->toPng()->save($path);
        $file = new UploadedFile($path, (string) $article['slug'].'.png', 'image/png', null, true);
        $stored = $images->store($file, (string) $article['coverAlt'], true);
        @unlink($path);

        return [
            'url' => $stored['url'],
            'width' => $stored['width'],
            'height' => $stored['height'],
        ];
    }
}
