<?php

namespace App\Modules\Blog;

use App\Exceptions\ApiException;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogPostRevision;
use App\Models\BlogReaction;
use App\Models\BlogSlugRedirect;
use App\Models\BlogTag;
use App\Models\Product;
use App\Models\User;
use App\Support\AdminPage;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlogService
{
    public function __construct(
        private readonly BlogImageService $images,
        private readonly BlogRevalidator $revalidator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function hub(Request $request): array
    {
        $page = AdminPage::query($request, 12);
        $paginator = $this->publishedQuery()->orderByDesc('published_at')->paginate($page['perPage'], ['*'], 'page', $page['page']);
        $this->assertPage($paginator, $page['page']);

        return [
            'categories' => $this->publicCategories(),
            'latest' => collect($paginator->items())->map(fn (BlogPost $post) => $this->toCard($post))->values()->all(),
            'popular' => $this->publishedQuery()->orderByDesc('views_count')->orderByDesc('published_at')->limit(6)->get()->map(fn (BlogPost $post) => $this->toCard($post))->values()->all(),
            'items' => collect($paginator->items())->map(fn (BlogPost $post) => $this->toCard($post))->values()->all(),
            'meta' => AdminPage::meta($paginator),
            'seo' => [
                'title' => 'وبلاگ شارژینو',
                'description' => 'آموزش خرید شارژ بازی و گیفت کارت در وبلاگ شارژینو؛ یوسی، سی‌پی و گیفت کارت با تومان، تحویل فوری، بدون رمز اکانت و پشتیبانی پاسخگو.',
                'noindex' => false,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function publicCategories(): array
    {
        $categories = BlogCategory::query()
            ->where('is_active', true)
            ->withCount(['posts as published_posts_count' => fn ($q) => $q->published()])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $categories->whereNull('parent_id')->values()->map(function (BlogCategory $category) use ($categories) {
            $children = $categories->where('parent_id', $category->id)->values();

            return $this->toPublicCategory($category, $children->all());
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function publicCategory(string $slug, Request $request): array
    {
        $category = BlogCategory::query()->where('slug', $slug)->where('is_active', true)->first();
        if (! $category) {
            $this->redirectOrFail(BlogSlugRedirect::TYPE_CATEGORY, $slug, '/blog/category/');
        }
        $ids = BlogCategory::query()
            ->where('id', $category->id)
            ->orWhere('parent_id', $category->id)
            ->pluck('id');
        $page = AdminPage::query($request, 12);
        $paginator = $this->publishedQuery()
            ->whereIn('category_id', $ids)
            ->orderByDesc('published_at')
            ->paginate($page['perPage'], ['*'], 'page', $page['page']);
        $this->assertPage($paginator, $page['page']);
        $count = (int) $paginator->total();

        return [
            'category' => $this->toPublicCategory($category, $category->children()->where('is_active', true)->get()->all(), $count),
            'items' => collect($paginator->items())->map(fn (BlogPost $post) => $this->toCard($post))->values()->all(),
            'meta' => AdminPage::meta($paginator),
            'seo' => $this->archiveSeo(
                $category->meta_title ?: $category->title.' | وبلاگ شارژینو',
                $category->meta_description ?: $category->description,
                $category->noindex || $count === 0,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicTag(string $slug, Request $request): array
    {
        $tag = BlogTag::query()->where('slug', $slug)->first();
        if (! $tag) {
            $this->redirectOrFail(BlogSlugRedirect::TYPE_TAG, $slug, '/blog/tag/');
        }
        $page = AdminPage::query($request, 12);
        $paginator = $this->publishedQuery()
            ->whereHas('tags', fn ($q) => $q->where('blog_tags.id', $tag->id))
            ->orderByDesc('published_at')
            ->paginate($page['perPage'], ['*'], 'page', $page['page']);
        $this->assertPage($paginator, $page['page']);
        $count = (int) $paginator->total();
        $this->refreshTagIndex($tag, $count);

        return [
            'tag' => $this->toPublicTag($tag, $count),
            'items' => collect($paginator->items())->map(fn (BlogPost $post) => $this->toCard($post))->values()->all(),
            'meta' => AdminPage::meta($paginator),
            'seo' => $this->archiveSeo(
                $tag->meta_title ?: $tag->title.' | وبلاگ شارژینو',
                $tag->meta_description ?: $tag->description,
                $tag->noindex || $count === 0,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicAuthor(string $slug, Request $request): array
    {
        $user = User::query()->where('blog_slug', $slug)->first();
        if (! $user && in_array($slug, ['charjino', 'شارژینو'], true)) {
            $user = User::query()->where('role', 'admin')->orderBy('id')->first();
        }
        if (! $user) {
            throw new ApiException('این نویسنده پیدا نشد.', 404, 'NOT_FOUND');
        }
        $page = AdminPage::query($request, 12);
        $paginator = $this->publishedQuery()
            ->where('user_id', $user->id)
            ->orderByDesc('published_at')
            ->paginate($page['perPage'], ['*'], 'page', $page['page']);
        $this->assertPage($paginator, $page['page']);

        return [
            'author' => $this->toPublicAuthor($user, (int) $paginator->total()),
            'items' => collect($paginator->items())->map(fn (BlogPost $post) => $this->toCard($post))->values()->all(),
            'meta' => AdminPage::meta($paginator),
            'seo' => $this->archiveSeo(
                'شارژینو؛ نویسندهٔ وبلاگ شارژینو',
                'مطالب آموزشی شارژ بازی و گیفت کارت به قلم شارژینو؛ خرید با تومان، تحویل فوری، بدون رمز اکانت و پشتیبانی پاسخگو.',
                (int) $paginator->total() === 0,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPosts(Request $request): array
    {
        $page = AdminPage::query($request, 12);
        $query = $this->publishedQuery();
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) >= 2) {
            $like = '%'.$q.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('title', 'like', $like)
                    ->orWhere('excerpt', 'like', $like)
                    ->orWhere('content', 'like', $like);
            });
        }
        $category = trim((string) $request->query('category', ''));
        if ($category !== '') {
            $ids = BlogCategory::query()
                ->where('slug', $category)
                ->orWhereHas('parent', fn ($p) => $p->where('slug', $category))
                ->pluck('id');
            $query->whereIn('category_id', $ids);
        }
        $tag = trim((string) $request->query('tag', ''));
        if ($tag !== '') {
            $query->whereHas('tags', fn ($inner) => $inner->where('blog_tags.slug', $tag));
        }
        $paginator = $query->orderByDesc('published_at')->paginate($page['perPage'], ['*'], 'page', $page['page']);
        $this->assertPage($paginator, $page['page']);

        return [
            'items' => collect($paginator->items())->map(fn (BlogPost $post) => $this->toCard($post))->values()->all(),
            'meta' => AdminPage::meta($paginator),
            'q' => $q,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function publicShow(string $slug, ?string $viewerId = null): array
    {
        $post = $this->publishedQuery()->where('slug', $slug)->with(['tags', 'products', 'author'])->first();
        if (! $post) {
            $this->redirectOrFail(BlogSlugRedirect::TYPE_POST, $slug, '/blog/');
        }
        BlogPost::query()->where('id', $post->id)->increment('views_count');
        $post->views_count = (int) $post->views_count + 1;
        $tagIds = $post->tags->pluck('id');
        $related = $this->publishedQuery()
            ->where('id', '!=', $post->id)
            ->where(function ($query) use ($post, $tagIds) {
                $query->where('category_id', $post->category_id);
                if ($tagIds->isNotEmpty()) {
                    $query->orWhereHas('tags', fn ($inner) => $inner->whereIn('blog_tags.id', $tagIds));
                }
            })
            ->orderByDesc('published_at')
            ->limit(4)
            ->get()
            ->unique('id')
            ->map(fn (BlogPost $item) => $this->toCard($item))
            ->values()
            ->all();

        return [
            'post' => $this->toPublicPost($post, $viewerId),
            'related' => $related,
            'products' => $this->relatedProducts($post),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sitemap(): array
    {
        $posts = $this->publishedQuery()
            ->where('noindex', false)
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at', 'published_at']);
        $categories = BlogCategory::query()
            ->where('is_active', true)
            ->where('noindex', false)
            ->withCount(['posts as published_posts_count' => fn ($q) => $q->published()])
            ->get()
            ->filter(fn (BlogCategory $category) => (int) $category->published_posts_count > 0);
        $tags = BlogTag::query()
            ->where('noindex', false)
            ->withCount(['posts as published_posts_count' => fn ($q) => $q->published()])
            ->get()
            ->filter(fn (BlogTag $tag) => (int) $tag->published_posts_count >= 3);

        return [
            'posts' => $posts->map(fn (BlogPost $post) => [
                'slug' => $post->slug,
                'lastmod' => ($post->updated_at ?? $post->published_at)?->toIso8601String(),
            ])->values()->all(),
            'categories' => $categories->map(fn (BlogCategory $category) => [
                'slug' => $category->slug,
                'lastmod' => $category->updated_at?->toIso8601String(),
            ])->values()->all(),
            'tags' => $tags->map(fn (BlogTag $tag) => [
                'slug' => $tag->slug,
                'lastmod' => $tag->updated_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adminCategories(): array
    {
        $items = BlogCategory::query()->with('parent')->orderBy('sort_order')->orderBy('id')->get();

        return ['items' => $items->map(fn (BlogCategory $category) => $this->toAdminCategory($category))->values()->all()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createCategory(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $slug = BlogSlug::uniqueCategory((string) ($data['slug'] ?? $title));
        $category = BlogCategory::query()->create([
            'title' => $title,
            'slug' => $slug,
            'description' => $this->nullableString($data['description'] ?? null, 500),
            'body_html' => BlogHtml::sanitize((string) ($data['bodyHtml'] ?? $data['body_html'] ?? '')),
            'meta_title' => $this->nullableString($data['metaTitle'] ?? $data['meta_title'] ?? null, 120),
            'meta_description' => $this->nullableString($data['metaDescription'] ?? $data['meta_description'] ?? null, 200),
            'noindex' => (bool) ($data['noindex'] ?? false),
            'image' => $this->nullableString($data['image'] ?? null, 2048),
            'image_alt' => $this->nullableString($data['imageAlt'] ?? $data['image_alt'] ?? null, 180),
            'parent_id' => $this->parentId($data['parentId'] ?? $data['parent_id'] ?? null),
            'is_active' => array_key_exists('isActive', $data) ? (bool) $data['isActive'] : true,
            'sort_order' => (int) ($data['sortOrder'] ?? $data['sort_order'] ?? 0),
        ]);
        $this->revalidator->ping(['blog:category:'.$category->slug]);

        return $this->toAdminCategory($category);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateCategory(BlogCategory $category, array $data): array
    {
        $oldSlug = $category->slug;
        if (array_key_exists('title', $data)) {
            $category->title = trim((string) $data['title']);
        }
        if (array_key_exists('slug', $data) || array_key_exists('title', $data)) {
            $category->slug = BlogSlug::uniqueCategory((string) ($data['slug'] ?? $category->title), $category->id);
        }
        if (array_key_exists('description', $data)) {
            $category->description = $this->nullableString($data['description'], 500);
        }
        if (array_key_exists('bodyHtml', $data) || array_key_exists('body_html', $data)) {
            $category->body_html = BlogHtml::sanitize((string) ($data['bodyHtml'] ?? $data['body_html'] ?? ''));
        }
        if (array_key_exists('metaTitle', $data) || array_key_exists('meta_title', $data)) {
            $category->meta_title = $this->nullableString($data['metaTitle'] ?? $data['meta_title'] ?? null, 120);
        }
        if (array_key_exists('metaDescription', $data) || array_key_exists('meta_description', $data)) {
            $category->meta_description = $this->nullableString($data['metaDescription'] ?? $data['meta_description'] ?? null, 200);
        }
        if (array_key_exists('noindex', $data)) {
            $category->noindex = (bool) $data['noindex'];
        }
        if (array_key_exists('image', $data)) {
            $category->image = $this->nullableString($data['image'], 2048);
        }
        if (array_key_exists('imageAlt', $data) || array_key_exists('image_alt', $data)) {
            $category->image_alt = $this->nullableString($data['imageAlt'] ?? $data['image_alt'] ?? null, 180);
        }
        if (array_key_exists('parentId', $data) || array_key_exists('parent_id', $data)) {
            $parentId = $this->parentId($data['parentId'] ?? $data['parent_id'] ?? null, $category->id);
            if ($parentId === $category->id) {
                throw new ApiException('دسته نمی‌تواند والد خودش باشد.', 422);
            }
            $category->parent_id = $parentId;
        }
        if (array_key_exists('isActive', $data)) {
            $category->is_active = (bool) $data['isActive'];
        }
        if (array_key_exists('sortOrder', $data) || array_key_exists('sort_order', $data)) {
            $category->sort_order = (int) ($data['sortOrder'] ?? $data['sort_order'] ?? 0);
        }
        $category->save();
        if ($oldSlug !== $category->slug) {
            BlogSlugRedirect::remember(BlogSlugRedirect::TYPE_CATEGORY, $oldSlug, $category->slug);
        }
        $this->revalidator->ping(['blog:category:'.$category->slug]);

        return $this->toAdminCategory($category->fresh('parent') ?? $category);
    }

    public function deleteCategory(BlogCategory $category): void
    {
        if ($category->posts()->exists() || $category->children()->exists()) {
            throw new ApiException('اول مطالب و زیردسته‌های این دسته را جابه‌جا یا حذف کن.', 409, 'CATEGORY_IN_USE');
        }
        $slug = $category->slug;
        $category->delete();
        $this->revalidator->ping(['blog:category:'.$slug]);
    }

    /**
     * @return array<string, mixed>
     */
    public function adminTags(): array
    {
        $items = BlogTag::query()->withCount(['posts as posts_count'])->orderBy('title')->get();

        return ['items' => $items->map(fn (BlogTag $tag) => $this->toAdminTag($tag))->values()->all()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createTag(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $tag = BlogTag::query()->create($this->tagPayload($data, $title, BlogSlug::uniqueTag((string) ($data['slug'] ?? $title))));
        $this->refreshTagIndex($tag);
        $this->revalidator->ping(['blog:tag:'.$tag->slug]);

        return $this->toAdminTag($tag);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateTag(BlogTag $tag, array $data): array
    {
        $oldSlug = $tag->slug;
        $title = array_key_exists('title', $data) ? trim((string) $data['title']) : $tag->title;
        $slug = BlogSlug::uniqueTag((string) ($data['slug'] ?? $title), $tag->id);
        $tag->fill($this->tagPayload($data, $title, $slug));
        $tag->save();
        if ($oldSlug !== $tag->slug) {
            BlogSlugRedirect::remember(BlogSlugRedirect::TYPE_TAG, $oldSlug, $tag->slug);
        }
        $this->refreshTagIndex($tag);
        $this->revalidator->ping(['blog:tag:'.$tag->slug]);

        return $this->toAdminTag($tag);
    }

    public function deleteTag(BlogTag $tag): void
    {
        if ($tag->posts()->exists()) {
            throw new ApiException('اول مطالب این تگ را جدا کن.', 409, 'TAG_IN_USE');
        }
        $slug = $tag->slug;
        $tag->delete();
        $this->revalidator->ping(['blog:tag:'.$slug]);
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array{page: int, perPage: int, total: int, totalPages: int}}
     */
    public function adminPosts(Request $request): array
    {
        $page = AdminPage::query($request, 20);
        $query = BlogPost::query()
            ->select([
                'id',
                'title',
                'slug',
                'excerpt',
                'cover',
                'cover_alt',
                'cover_width',
                'cover_height',
                'category_id',
                'user_id',
                'status',
                'published_at',
                'meta_title',
                'meta_description',
                'canonical_url',
                'noindex',
                'focus_keyword',
                'og_title',
                'og_description',
                'og_image',
                'twitter_title',
                'twitter_description',
                'twitter_image',
                'secondary_keywords',
                'faq',
                'preview_token',
                'views_count',
                'likes_count',
                'comments_count',
                'reading_minutes',
                'created_at',
                'updated_at',
            ])
            ->with(['category', 'tags', 'author']);
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('title', 'like', $like)->orWhere('slug', 'like', $like);
            });
        }
        $status = trim((string) $request->query('status', ''));
        if (in_array($status, [BlogPost::STATUS_DRAFT, BlogPost::STATUS_PUBLISHED, BlogPost::STATUS_SCHEDULED], true)) {
            $query->where('status', $status);
        }
        $categoryId = (int) $request->query('categoryId', $request->query('category_id', 0));
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }
        $paginator = $query->orderByDesc('id')->paginate($page['perPage'], ['*'], 'page', $page['page']);

        return [
            'items' => collect($paginator->items())->map(fn (BlogPost $post) => $this->toAdminPost($post, false))->values()->all(),
            'meta' => AdminPage::meta($paginator),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adminShow(BlogPost $post): array
    {
        $post->load(['category', 'tags', 'products', 'author', 'revisions' => fn ($q) => $q->orderByDesc('id')->limit(10)]);
        if (! $post->preview_token) {
            $post->preview_token = $this->newPreviewToken();
            $post->save();
        }

        return $this->toAdminPost($post, true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPost(array $data, string $userId): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $raw = (string) ($data['content'] ?? '');
        $publishing = $this->isPublishing($data);
        if ($publishing) {
            BlogContentRules::assertPublishable($raw);
        } elseif (trim($raw) !== '') {
            BlogContentRules::assertStructure($raw);
        }
        $content = BlogHtml::sanitize($raw);
        $excerpt = $this->nullableString($data['excerpt'] ?? null, 500)
            ?: ($content !== '' ? BlogHtml::excerpt($content) : null);
        $categoryId = $this->nullableInt($data['categoryId'] ?? $data['category_id'] ?? null);
        if ($publishing && ! $categoryId) {
            throw new ApiException('برای انتشار، دسته لازم است.', 422, 'CATEGORY_REQUIRED');
        }
        $post = BlogPost::query()->create(array_merge([
            'title' => $title,
            'slug' => BlogSlug::uniquePost((string) ($data['slug'] ?? $title)),
            'excerpt' => $excerpt,
            'content' => $content,
            'cover' => $this->nullableString($data['cover'] ?? null, 2048),
            'cover_alt' => $this->nullableString($data['coverAlt'] ?? $data['cover_alt'] ?? null, 180),
            'cover_width' => $this->nullableInt($data['coverWidth'] ?? $data['cover_width'] ?? null),
            'cover_height' => $this->nullableInt($data['coverHeight'] ?? $data['cover_height'] ?? null),
            'category_id' => $categoryId,
            'user_id' => $this->authorId($data, $userId),
            'status' => BlogPost::STATUS_DRAFT,
            'meta_title' => $this->nullableString($data['metaTitle'] ?? $data['meta_title'] ?? null, 120),
            'meta_description' => $this->nullableString($data['metaDescription'] ?? $data['meta_description'] ?? null, 200),
            'canonical_url' => $this->nullableString($data['canonicalUrl'] ?? $data['canonical_url'] ?? null, 2048),
            'noindex' => (bool) ($data['noindex'] ?? false),
            'focus_keyword' => $this->nullableString($data['focusKeyword'] ?? $data['focus_keyword'] ?? null, 80),
            'faq' => $this->faq($data['faq'] ?? null),
            'preview_token' => $this->newPreviewToken(),
            'reading_minutes' => $content !== '' ? BlogHtml::readingMinutes($content) : 1,
        ], $this->socialPayload($data)));
        $this->syncRelations($post, $data);
        $this->ensureAuthorSlug($post);

        if (! empty($data['publish']) || (($data['status'] ?? '') === BlogPost::STATUS_PUBLISHED)) {
            return $this->publish($post, $userId, $data);
        }
        if (($data['status'] ?? '') === BlogPost::STATUS_SCHEDULED) {
            return $this->schedule($post, $data, $userId);
        }

        return $this->toAdminPost($post->fresh(['category', 'tags', 'products', 'author']) ?? $post, true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updatePost(BlogPost $post, array $data): array
    {
        $this->storeRevision($post);
        $oldSlug = $post->slug;
        $wasLive = $post->isLive();
        if (array_key_exists('title', $data)) {
            $post->title = trim((string) $data['title']);
        }
        if (array_key_exists('slug', $data) || array_key_exists('title', $data)) {
            $post->slug = BlogSlug::uniquePost((string) ($data['slug'] ?? $post->title), $post->id);
        }
        if (array_key_exists('content', $data)) {
            $raw = (string) $data['content'];
            if ($this->isPublishing($data)) {
                BlogContentRules::assertPublishable($raw);
            } elseif (trim($raw) !== '') {
                BlogContentRules::assertStructure($raw);
            }
            $post->content = BlogHtml::sanitize($raw);
            $post->reading_minutes = BlogHtml::readingMinutes((string) $post->content);
        }
        if (array_key_exists('excerpt', $data)) {
            $excerpt = $this->nullableString($data['excerpt'], 500);
            $post->excerpt = $excerpt ?: (trim((string) $post->content) !== '' ? BlogHtml::excerpt((string) $post->content) : null);
        }
        if (array_key_exists('cover', $data)) {
            $post->cover = $this->nullableString($data['cover'], 2048);
        }
        if (array_key_exists('coverAlt', $data) || array_key_exists('cover_alt', $data)) {
            $post->cover_alt = $this->nullableString($data['coverAlt'] ?? $data['cover_alt'] ?? null, 180);
        }
        if (array_key_exists('coverWidth', $data) || array_key_exists('cover_width', $data)) {
            $post->cover_width = $this->nullableInt($data['coverWidth'] ?? $data['cover_width'] ?? null);
        }
        if (array_key_exists('coverHeight', $data) || array_key_exists('cover_height', $data)) {
            $post->cover_height = $this->nullableInt($data['coverHeight'] ?? $data['cover_height'] ?? null);
        }
        if (array_key_exists('categoryId', $data) || array_key_exists('category_id', $data)) {
            $post->category_id = $this->nullableInt($data['categoryId'] ?? $data['category_id'] ?? null);
        }
        if (array_key_exists('userId', $data) || array_key_exists('user_id', $data)) {
            $post->user_id = $this->authorId($data, (string) $post->user_id);
        }
        foreach ([
            'meta_title' => ['metaTitle', 'meta_title', 120],
            'meta_description' => ['metaDescription', 'meta_description', 200],
            'canonical_url' => ['canonicalUrl', 'canonical_url', 2048],
            'focus_keyword' => ['focusKeyword', 'focus_keyword', 80],
        ] as $column => [$camel, $snake, $max]) {
            if (array_key_exists($camel, $data) || array_key_exists($snake, $data)) {
                $post->{$column} = $this->nullableString($data[$camel] ?? $data[$snake] ?? null, $max);
            }
        }
        if (array_key_exists('noindex', $data)) {
            $post->noindex = (bool) $data['noindex'];
        }
        if (array_key_exists('faq', $data)) {
            $post->faq = $this->faq($data['faq']);
        }
        foreach ($this->socialPayload($data, true) as $column => $value) {
            $post->{$column} = $value;
        }
        if (! $post->preview_token) {
            $post->preview_token = $this->newPreviewToken();
        }
        if ($this->isPublishing($data) && ! $post->category_id) {
            throw new ApiException('برای انتشار، دسته لازم است.', 422, 'CATEGORY_REQUIRED');
        }
        $post->save();
        $this->syncRelations($post, $data);
        if ($wasLive && $oldSlug !== $post->slug) {
            BlogSlugRedirect::remember(BlogSlugRedirect::TYPE_POST, $oldSlug, $post->slug);
        }
        $this->ensureAuthorSlug($post);

        if (! empty($data['publish']) || (($data['status'] ?? '') === BlogPost::STATUS_PUBLISHED)) {
            return $this->publish($post, (string) $post->user_id, $data);
        }
        if (($data['status'] ?? '') === BlogPost::STATUS_SCHEDULED) {
            return $this->schedule($post, $data, (string) $post->user_id);
        }
        if (array_key_exists('publish', $data) && $data['publish'] === false && $post->status === BlogPost::STATUS_PUBLISHED) {
            return $this->unpublish($post);
        }
        if (($data['status'] ?? '') === BlogPost::STATUS_DRAFT && $post->status !== BlogPost::STATUS_DRAFT) {
            return $this->unpublish($post);
        }
        $fresh = $post->fresh(['category', 'tags', 'products', 'author', 'revisions']);
        $this->revalidator->pingPost($fresh);

        return $this->toAdminPost($fresh ?? $post, true);
    }

    public function deletePost(BlogPost $post): void
    {
        $post->load(['category', 'tags', 'author']);
        $this->revalidator->pingPost($post);
        $post->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function publish(BlogPost $post, string $userId, array $data = []): array
    {
        $this->assertReady($post);
        $when = $this->publishedAt($data['publishedAt'] ?? $data['published_at'] ?? null) ?? $post->published_at ?? now();
        if ($when->gt(now())) {
            return $this->schedule($post, ['publishedAt' => $when->toIso8601String()], $userId);
        }
        $post->status = BlogPost::STATUS_PUBLISHED;
        $post->published_at = $when;
        if (! $post->user_id) {
            $post->user_id = $userId;
        }
        if (! $post->meta_title) {
            $post->meta_title = BlogHtml::defaultMetaTitle((string) $post->title);
        }
        $post->save();
        $this->ensureAuthorSlug($post);
        $fresh = $post->fresh(['category', 'tags', 'products', 'author', 'revisions']) ?? $post;
        $this->revalidator->pingPost($fresh);

        return $this->toAdminPost($fresh, true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function schedule(BlogPost $post, array $data, string $userId): array
    {
        $this->assertReady($post);
        $when = $this->publishedAt($data['publishedAt'] ?? $data['published_at'] ?? null);
        if (! $when || $when->lte(now())) {
            throw new ApiException('برای زمان‌بندی، تاریخ انتشار آینده لازم است.', 422, 'BLOG_SCHEDULE');
        }
        $post->status = BlogPost::STATUS_SCHEDULED;
        $post->published_at = $when;
        if (! $post->user_id) {
            $post->user_id = $userId;
        }
        $post->save();
        $this->ensureAuthorSlug($post);

        return $this->toAdminPost($post->fresh(['category', 'tags', 'products', 'author']) ?? $post, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function unpublish(BlogPost $post): array
    {
        $post->status = BlogPost::STATUS_DRAFT;
        $post->save();
        $fresh = $post->fresh(['category', 'tags', 'products', 'author']) ?? $post;
        $this->revalidator->pingPost($fresh);

        return $this->toAdminPost($fresh, true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function revisions(BlogPost $post): array
    {
        return $post->revisions()->orderByDesc('id')->limit(10)->get()->map(fn (BlogPostRevision $revision) => [
            'id' => $revision->id,
            'createdAt' => $revision->created_at?->toIso8601String(),
            'title' => $revision->payload['title'] ?? null,
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function restoreRevision(BlogPost $post, BlogPostRevision $revision): array
    {
        if ((int) $revision->post_id !== (int) $post->id) {
            throw new ApiException('این نسخه مربوط به این مطلب نیست.', 404, 'NOT_FOUND');
        }
        $payload = is_array($revision->payload) ? $revision->payload : [];
        $payload['status'] = BlogPost::STATUS_DRAFT;
        $payload['publish'] = false;

        return $this->updatePost($post, [
            'title' => $payload['title'] ?? $post->title,
            'slug' => $payload['slug'] ?? $post->slug,
            'excerpt' => $payload['excerpt'] ?? $post->excerpt,
            'content' => $payload['content'] ?? $post->content,
            'cover' => $payload['cover'] ?? $post->cover,
            'coverAlt' => $payload['coverAlt'] ?? $post->cover_alt,
            'categoryId' => $payload['categoryId'] ?? $post->category_id,
            'metaTitle' => $payload['metaTitle'] ?? $post->meta_title,
            'metaDescription' => $payload['metaDescription'] ?? $post->meta_description,
            'canonicalUrl' => $payload['canonicalUrl'] ?? $post->canonical_url,
            'noindex' => $payload['noindex'] ?? $post->noindex,
            'focusKeyword' => $payload['focusKeyword'] ?? $post->focus_keyword,
            'faq' => $payload['faq'] ?? $post->faq,
            'tagIds' => $payload['tagIds'] ?? [],
            'productIds' => $payload['productIds'] ?? [],
            'status' => BlogPost::STATUS_DRAFT,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function linkSearch(string $q): array
    {
        $like = '%'.trim($q).'%';
        $posts = $this->publishedQuery()->where('title', 'like', $like)->limit(8)->get();
        $products = Product::query()
            ->where('visible', true)
            ->where('status', 'active')
            ->where('title', 'like', $like)
            ->limit(8)
            ->get();

        return [
            'posts' => $posts->map(fn (BlogPost $post) => [
                'title' => $post->title,
                'slug' => $post->slug,
                'href' => '/blog/'.$post->slug,
            ])->values()->all(),
            'products' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'title' => $product->title,
                'handle' => $product->handle,
                'href' => '/catalog/'.$product->handle,
            ])->values()->all(),
        ];
    }

    public function slugAvailable(string $slug, ?int $ignoreId = null): bool
    {
        return ! BlogSlug::postTaken(BlogSlug::make($slug), $ignoreId);
    }

    /**
     * @return array<string, mixed>
     */
    public function togglePostLike(BlogPost $post, string $userId): array
    {
        if (! $post->isLive()) {
            throw new ApiException('این مطلب پیدا نشد.', 404, 'NOT_FOUND');
        }
        $liked = $this->toggleReaction(BlogReaction::TYPE_POST, (int) $post->id, $userId);
        $post->likes_count = BlogReaction::query()
            ->where('reactable_type', BlogReaction::TYPE_POST)
            ->where('reactable_id', $post->id)
            ->count();
        $post->save();

        return [
            'liked' => $liked,
            'likesCount' => (int) $post->likes_count,
        ];
    }

    public function publishedBySlug(string $slug): BlogPost
    {
        $post = $this->publishedQuery()->where('slug', $slug)->first();
        if (! $post) {
            throw new ApiException('این مطلب پیدا نشد.', 404, 'NOT_FOUND');
        }

        return $post;
    }

    /**
     * @return list<int>
     */
    public function likedPostIds(string $userId, array $postIds): array
    {
        if ($userId === '' || $postIds === []) {
            return [];
        }

        return BlogReaction::query()
            ->where('user_id', $userId)
            ->where('reactable_type', BlogReaction::TYPE_POST)
            ->whereIn('reactable_id', $postIds)
            ->pluck('reactable_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function toggleReaction(string $type, int $id, string $userId): bool
    {
        return DB::transaction(function () use ($type, $id, $userId) {
            $existing = BlogReaction::query()
                ->where('user_id', $userId)
                ->where('reactable_type', $type)
                ->where('reactable_id', $id)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $existing->delete();

                return false;
            }
            BlogReaction::query()->create([
                'user_id' => $userId,
                'reactable_type' => $type,
                'reactable_id' => $id,
                'vote' => BlogReaction::VOTE_LIKE,
            ]);

            return true;
        });
    }

    /**
     * @return Builder<BlogPost>
     */
    private function publishedQuery()
    {
        return BlogPost::query()->published()->with(['category', 'author']);
    }

    /**
     * @param  list<BlogCategory>  $children
     * @return array<string, mixed>
     */
    private function toPublicCategory(BlogCategory $category, array $children = [], ?int $postsCount = null): array
    {
        return [
            'id' => $category->id,
            'title' => $category->title,
            'slug' => $category->slug,
            'description' => $category->description,
            'bodyHtml' => $category->body_html,
            'image' => $category->image,
            'imageAlt' => $category->image_alt,
            'noindex' => (bool) $category->noindex || (($postsCount ?? (int) ($category->published_posts_count ?? 0)) === 0),
            'postsCount' => $postsCount ?? (int) ($category->published_posts_count ?? 0),
            'children' => array_map(fn (BlogCategory $child) => [
                'id' => $child->id,
                'title' => $child->title,
                'slug' => $child->slug,
            ], $children),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toPublicTag(BlogTag $tag, int $count): array
    {
        return [
            'id' => $tag->id,
            'title' => $tag->title,
            'slug' => $tag->slug,
            'description' => $tag->description,
            'bodyHtml' => $tag->body_html,
            'image' => $tag->image,
            'noindex' => (bool) $tag->noindex || $count < 3,
            'postsCount' => $count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toCard(BlogPost $post, bool $liked = false): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt ?: BlogHtml::excerpt((string) $post->content),
            'cover' => $post->cover,
            'coverAlt' => $post->cover_alt ?: $post->title,
            'publishedAt' => $post->published_at?->toIso8601String(),
            'updatedAt' => $post->updated_at?->toIso8601String(),
            'viewsCount' => (int) $post->views_count,
            'likesCount' => (int) $post->likes_count,
            'commentsCount' => (int) $post->comments_count,
            'readingMinutes' => (int) $post->reading_minutes ?: BlogHtml::readingMinutes((string) $post->content),
            'liked' => $liked,
            'category' => $post->category ? [
                'id' => $post->category->id,
                'title' => $post->category->title,
                'slug' => $post->category->slug,
            ] : null,
            'author' => $this->toPublicAuthor($post->author),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicPost(BlogPost $post, ?string $viewerId = null): array
    {
        $liked = false;
        if ($viewerId) {
            $liked = BlogReaction::query()
                ->where('user_id', $viewerId)
                ->where('reactable_type', BlogReaction::TYPE_POST)
                ->where('reactable_id', $post->id)
                ->exists();
        }
        $card = $this->toCard($post, $liked);
        $card['content'] = (string) $post->content;
        $card['metaTitle'] = $post->meta_title ?: BlogHtml::defaultMetaTitle((string) $post->title);
        $card['metaDescription'] = $post->meta_description ?: $card['excerpt'];
        $card['canonicalUrl'] = $post->canonical_url;
        $card['noindex'] = (bool) $post->noindex;
        $card['faq'] = $this->faq($post->faq) ?? [];
        $card['ogTitle'] = $post->og_title;
        $card['ogDescription'] = $post->og_description;
        $card['ogImage'] = $post->og_image;
        $card['twitterTitle'] = $post->twitter_title;
        $card['twitterDescription'] = $post->twitter_description;
        $card['twitterImage'] = $post->twitter_image;
        $card['secondaryKeywords'] = is_array($post->secondary_keywords) ? array_values($post->secondary_keywords) : [];
        $card['tags'] = $post->relationLoaded('tags')
            ? $post->tags->map(fn (BlogTag $tag) => ['title' => $tag->title, 'slug' => $tag->slug])->values()->all()
            : [];
        $card['coverWidth'] = $post->cover_width;
        $card['coverHeight'] = $post->cover_height;

        return $card;
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminCategory(BlogCategory $category): array
    {
        return [
            'id' => $category->id,
            'title' => $category->title,
            'slug' => $category->slug,
            'description' => $category->description,
            'bodyHtml' => $category->body_html,
            'metaTitle' => $category->meta_title,
            'metaDescription' => $category->meta_description,
            'noindex' => (bool) $category->noindex,
            'image' => $category->image,
            'imageAlt' => $category->image_alt,
            'parentId' => $category->parent_id,
            'parentTitle' => $category->parent?->title,
            'isActive' => (bool) $category->is_active,
            'sortOrder' => (int) $category->sort_order,
            'updatedAt' => $category->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminTag(BlogTag $tag): array
    {
        return [
            'id' => $tag->id,
            'title' => $tag->title,
            'slug' => $tag->slug,
            'description' => $tag->description,
            'bodyHtml' => $tag->body_html,
            'image' => $tag->image,
            'imageAlt' => $tag->image_alt,
            'metaTitle' => $tag->meta_title,
            'metaDescription' => $tag->meta_description,
            'forceNoindex' => (bool) $tag->force_noindex,
            'noindex' => (bool) $tag->noindex,
            'postsCount' => (int) ($tag->posts_count ?? $tag->posts()->count()),
            'updatedAt' => $tag->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminPost(BlogPost $post, bool $withContent = false): array
    {
        $data = [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'cover' => $post->cover,
            'coverAlt' => $post->cover_alt,
            'coverWidth' => $post->cover_width,
            'coverHeight' => $post->cover_height,
            'categoryId' => $post->category_id,
            'categoryTitle' => $post->category?->title,
            'userId' => $post->user_id,
            'authorName' => $post->author?->name,
            'status' => $post->status,
            'publishedAt' => $post->published_at?->toIso8601String(),
            'metaTitle' => $post->meta_title,
            'metaDescription' => $post->meta_description,
            'canonicalUrl' => $post->canonical_url,
            'noindex' => (bool) $post->noindex,
            'focusKeyword' => $post->focus_keyword,
            'ogTitle' => $post->og_title,
            'ogDescription' => $post->og_description,
            'ogImage' => $post->og_image,
            'twitterTitle' => $post->twitter_title,
            'twitterDescription' => $post->twitter_description,
            'twitterImage' => $post->twitter_image,
            'secondaryKeywords' => is_array($post->secondary_keywords) ? array_values($post->secondary_keywords) : [],
            'faq' => $this->faq($post->faq) ?? [],
            'previewToken' => $post->preview_token,
            'tagIds' => $post->relationLoaded('tags') ? $post->tags->pluck('id')->map(fn ($id) => (int) $id)->all() : [],
            'tags' => $post->relationLoaded('tags')
                ? $post->tags->map(fn (BlogTag $tag) => ['id' => $tag->id, 'title' => $tag->title, 'slug' => $tag->slug])->values()->all()
                : [],
            'productIds' => $post->relationLoaded('products') ? $post->products->pluck('id')->map(fn ($id) => (int) $id)->all() : [],
            'viewsCount' => (int) $post->views_count,
            'likesCount' => (int) $post->likes_count,
            'commentsCount' => (int) $post->comments_count,
            'readingMinutes' => (int) $post->reading_minutes,
            'updatedAt' => $post->updated_at?->toIso8601String(),
        ];
        if ($withContent) {
            $data['content'] = (string) $post->content;
            $data['revisions'] = $post->relationLoaded('revisions')
                ? $post->revisions->take(10)->map(fn (BlogPostRevision $revision) => [
                    'id' => $revision->id,
                    'createdAt' => $revision->created_at?->toIso8601String(),
                    'title' => $revision->payload['title'] ?? null,
                ])->values()->all()
                : [];
        }

        return $data;
    }

    private function parentId(mixed $value, ?int $selfId = null): ?int
    {
        $id = (int) $value;
        if ($id <= 0) {
            return null;
        }
        $parent = BlogCategory::query()->find($id);
        if (! $parent) {
            throw new ApiException('دسته والد پیدا نشد.', 422);
        }
        if ($parent->parent_id) {
            throw new ApiException('حداکثر دو سطح دسته مجاز است.', 422);
        }
        if ($selfId && BlogCategory::query()->where('parent_id', $selfId)->exists()) {
            throw new ApiException('دسته‌ای که زیردسته دارد نمی‌تواند خودش زیردسته شود.', 422);
        }

        return $id;
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $max);
    }

    private function nullableInt(mixed $value): ?int
    {
        $n = (int) $value;

        return $n > 0 ? $n : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function authorId(array $data, string $fallback): string
    {
        $id = trim((string) ($data['userId'] ?? $data['user_id'] ?? $fallback));

        return $id !== '' ? $id : $fallback;
    }

    /**
     * @return list<array{question: string, answer: string}>|null
     */
    private function faq(mixed $value): ?array
    {
        return BlogFaq::parse($value);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isPublishing(array $data): bool
    {
        return ! empty($data['publish'])
            || in_array($data['status'] ?? '', [BlogPost::STATUS_PUBLISHED, BlogPost::STATUS_SCHEDULED], true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function socialPayload(array $data, bool $partial = false): array
    {
        $map = [
            'og_title' => ['ogTitle', 'og_title', 120],
            'og_description' => ['ogDescription', 'og_description', 200],
            'og_image' => ['ogImage', 'og_image', 2048],
            'twitter_title' => ['twitterTitle', 'twitter_title', 120],
            'twitter_description' => ['twitterDescription', 'twitter_description', 200],
            'twitter_image' => ['twitterImage', 'twitter_image', 2048],
        ];
        $out = [];
        foreach ($map as $column => [$camel, $snake, $max]) {
            if (! $partial || array_key_exists($camel, $data) || array_key_exists($snake, $data)) {
                $out[$column] = $this->nullableString($data[$camel] ?? $data[$snake] ?? null, $max);
            }
        }
        if (! $partial || array_key_exists('secondaryKeywords', $data) || array_key_exists('secondary_keywords', $data)) {
            $out['secondary_keywords'] = $this->keywords($data['secondaryKeywords'] ?? $data['secondary_keywords'] ?? null);
        }

        return $out;
    }

    /**
     * @return list<string>|null
     */
    private function keywords(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = preg_split('/[,،]+/u', $value) ?: [];
        }
        if (! is_array($value)) {
            return null;
        }
        $out = [];
        foreach ($value as $item) {
            $text = trim(strip_tags((string) $item));
            if ($text !== '') {
                $out[] = mb_substr($text, 0, 80);
            }
        }

        return $out !== [] ? array_values(array_unique($out)) : null;
    }

    private function newPreviewToken(): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while (BlogPost::query()->where('preview_token', $token)->exists());

        return $token;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(BlogPost $post, array $data): void
    {
        if (array_key_exists('tagIds', $data) || array_key_exists('tag_ids', $data)) {
            $ids = collect($data['tagIds'] ?? $data['tag_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->all();
            $post->tags()->sync($ids);
            foreach (BlogTag::query()->whereIn('id', $ids)->get() as $tag) {
                $this->refreshTagIndex($tag);
            }
        }
        if (array_key_exists('productIds', $data) || array_key_exists('product_ids', $data)) {
            $ids = collect($data['productIds'] ?? $data['product_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->all();
            $post->products()->sync($ids);
        }
    }

    private function refreshTagIndex(BlogTag $tag, ?int $count = null): void
    {
        $published = $count ?? $tag->posts()->published()->count();
        $tag->noindex = $tag->force_noindex || $published < 3;
        $tag->save();
    }

    private function storeRevision(BlogPost $post): void
    {
        $post->loadMissing(['tags', 'products']);
        BlogPostRevision::query()->create([
            'post_id' => $post->id,
            'user_id' => $post->user_id,
            'payload' => $this->toAdminPost($post, true),
        ]);
        $keep = BlogPostRevision::query()
            ->where('post_id', $post->id)
            ->orderByDesc('id')
            ->limit(10)
            ->pluck('id');
        BlogPostRevision::query()
            ->where('post_id', $post->id)
            ->whereNotIn('id', $keep)
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPreview(string $token): array
    {
        $clean = trim($token);
        $post = $clean !== ''
            ? BlogPost::query()->where('preview_token', $clean)->with(['category', 'tags', 'products', 'author'])->first()
            : null;
        if (! $post) {
            throw new ApiException('این پیش‌نمایش پیدا نشد.', 404, 'NOT_FOUND');
        }

        return [
            'post' => $this->toPublicPost($post),
            'related' => [],
            'products' => $this->relatedProducts($post),
            'preview' => true,
        ];
    }

    private function assertReady(BlogPost $post): void
    {
        if (! $post->category_id) {
            throw new ApiException('برای انتشار، دسته لازم است.', 422, 'CATEGORY_REQUIRED');
        }
        if (trim((string) $post->content) === '') {
            throw new ApiException('متن مطلب خالی است.', 422);
        }
        $titleLen = mb_strlen(trim((string) $post->title));
        if ($titleLen < 10 || $titleLen > 100) {
            throw new ApiException('عنوان باید بین ۱۰ تا ۱۰۰ کاراکتر باشد.', 422);
        }
        BlogContentRules::assertPublishable((string) $post->content);
        $meta = $post->meta_description ?: $post->excerpt;
        BlogContentRules::assertMetaDescription($meta ?: null);
        if (! $post->cover_alt) {
            throw new ApiException('متن جایگزین تصویر شاخص لازم است.', 422, 'COVER_ALT');
        }
        $this->images->assertCover($post->cover, $post->cover_width, $post->cover_height);
    }

    private function publishedAt(mixed $value): ?CarbonInterface
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            throw new ApiException('تاریخ انتشار نامعتبر است.', 422);
        }
    }

    private function ensureAuthorSlug(BlogPost $post): void
    {
        if (! $post->user_id) {
            return;
        }
        $user = $post->author ?: User::query()->find($post->user_id);
        if (! $user) {
            return;
        }
        if ($user->blog_slug) {
            return;
        }
        $user->blog_slug = BlogSlug::uniqueAuthor($user->name ?: 'author', $user->id);
        $user->save();
        $post->setRelation('author', $user);
    }

    /**
     * @return array<string, mixed>
     */
    private function toPublicAuthor(?User $user, int $postsCount = 0): array
    {
        return [
            'name' => 'شارژینو',
            'slug' => 'charjino',
            'postsCount' => $postsCount,
            'url' => '/about',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function relatedProducts(BlogPost $post): array
    {
        if (! $post->relationLoaded('products')) {
            $post->load('products');
        }

        return $post->products
            ->filter(fn (Product $product) => $product->storefrontAvailable())
            ->take(6)
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'handle' => $product->handle,
                'title' => $product->title,
                'image' => $product->displayImage(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function tagPayload(array $data, string $title, string $slug): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'description' => $this->nullableString($data['description'] ?? null, 500),
            'body_html' => BlogHtml::sanitize((string) ($data['bodyHtml'] ?? $data['body_html'] ?? '')),
            'image' => $this->nullableString($data['image'] ?? null, 2048),
            'image_alt' => $this->nullableString($data['imageAlt'] ?? $data['image_alt'] ?? null, 180),
            'meta_title' => $this->nullableString($data['metaTitle'] ?? $data['meta_title'] ?? null, 120),
            'meta_description' => $this->nullableString($data['metaDescription'] ?? $data['meta_description'] ?? null, 200),
            'force_noindex' => (bool) ($data['forceNoindex'] ?? $data['force_noindex'] ?? $data['noindex'] ?? false),
        ];
    }

    /**
     * @return array{title: string, description: string, noindex: bool}
     */
    private function archiveSeo(string $title, ?string $description, bool $noindex): array
    {
        return [
            'title' => $title,
            'description' => $this->fillMetaDescription($description),
            'noindex' => $noindex,
        ];
    }

    private function fillMetaDescription(?string $description): string
    {
        $next = trim(preg_replace('/\s+/u', ' ', (string) $description) ?? '');
        $pads = [
            ' پرداخت ریالی است.',
            ' بدون رمز اکانت بازی.',
            ' سفارش فقط از خود سایت ثبت می‌شود.',
            ' پشتیبانی از همین سایت پاسخ می‌دهد.',
            ' تحویل دیجیتال از شارژینو.',
        ];
        foreach ($pads as $pad) {
            if (mb_strlen($next) >= 110) {
                break;
            }
            $stem = trim(preg_replace('/[.،؛]+$/u', '', $pad) ?? '');
            $words = preg_split('/\s+/u', $stem) ?: [];
            $prefix = trim(implode(' ', array_slice($words, 0, 2)));
            if ($prefix !== '' && str_contains($next, $prefix)) {
                continue;
            }
            $candidate = $next === '' ? trim($pad) : $next.$pad;
            if (mb_strlen($candidate) <= 160) {
                $next = $candidate;
            }
        }
        if (mb_strlen($next) < 110) {
            $next = 'مطالب آموزشی شارژ بازی و گیفت کارت در وبلاگ شارژینو؛ خرید با تومان، تحویل فوری، بدون رمز اکانت و پشتیبانی پاسخگو.';
        }
        if (mb_strlen($next) > 160) {
            $next = rtrim(mb_substr($next, 0, 159)).'…';
        }

        return $next;
    }

    private function assertPage(LengthAwarePaginator $paginator, int $page): void
    {
        if ($page > 1 && ($paginator->isEmpty() || $page > $paginator->lastPage())) {
            throw new ApiException('این صفحه پیدا نشد.', 404, 'NOT_FOUND');
        }
    }

    private function redirectOrFail(string $type, string $slug, string $prefix): never
    {
        $to = BlogSlugRedirect::resolve($type, $slug);
        if ($to) {
            throw new ApiException('آدرس عوض شده است.', 301, 'SLUG_REDIRECT', null, ['to' => $prefix.$to]);
        }
        throw new ApiException('این صفحه پیدا نشد.', 404, 'NOT_FOUND');
    }
}
