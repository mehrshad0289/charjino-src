<?php

namespace App\Modules\Blog;

use App\Exceptions\ApiException;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogReaction;
use App\Support\AdminPage;
use Illuminate\Http\Request;

class BlogCommentService
{
    public function __construct(private readonly BlogService $posts) {}

    /**
     * @return array<string, mixed>
     */
    public function publicList(BlogPost $post, Request $request, ?string $viewerId = null): array
    {
        $page = AdminPage::query($request, 20);
        $paginator = BlogComment::query()
            ->approved()
            ->where('post_id', $post->id)
            ->whereNull('parent_id')
            ->with([
                'user:id,name,phone',
                'replies' => fn ($q) => $q->approved()->with('user:id,name,phone'),
            ])
            ->orderByDesc('id')
            ->paginate($page['perPage'], ['*'], 'page', $page['page']);
        $ids = [];
        foreach ($paginator->items() as $comment) {
            $ids[] = (int) $comment->id;
            foreach ($comment->replies as $reply) {
                $ids[] = (int) $reply->id;
            }
        }
        $liked = $this->likedCommentIds($viewerId, $ids);

        return [
            'items' => collect($paginator->items())->map(fn (BlogComment $comment) => $this->toPublic($comment, $liked))->values()->all(),
            'meta' => AdminPage::meta($paginator),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function create(BlogPost $post, string $userId, string $body, ?int $parentId): array
    {
        if (! $post->isPublished()) {
            throw new ApiException('این مطلب پیدا نشد.', 404, 'NOT_FOUND');
        }
        $text = trim($body);
        if (mb_strlen($text) < 3) {
            throw new ApiException('متن نظر خیلی کوتاه است.', 422);
        }
        if (mb_strlen($text) > 1000) {
            throw new ApiException('متن نظر حداکثر ۱۰۰۰ کاراکتر است.', 422);
        }
        $parent = null;
        if ($parentId) {
            $parent = BlogComment::query()
                ->approved()
                ->where('post_id', $post->id)
                ->where('id', $parentId)
                ->first();
            if (! $parent || ! $parent->isRoot()) {
                throw new ApiException('پاسخ فقط روی نظر اصلی مجاز است.', 422);
            }
        }
        $comment = BlogComment::query()->create([
            'post_id' => $post->id,
            'user_id' => $userId,
            'parent_id' => $parent?->id,
            'body' => $text,
            'status' => BlogComment::STATUS_PENDING,
        ]);
        $comment->load('user:id,name,phone');

        return $this->toPublic($comment, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function toggleLike(BlogComment $comment, string $userId): array
    {
        if ($comment->status !== BlogComment::STATUS_APPROVED) {
            throw new ApiException('این نظر پیدا نشد.', 404, 'NOT_FOUND');
        }
        $liked = $this->posts->toggleReaction(BlogReaction::TYPE_COMMENT, (int) $comment->id, $userId);
        $comment->likes_count = BlogReaction::query()
            ->where('reactable_type', BlogReaction::TYPE_COMMENT)
            ->where('reactable_id', $comment->id)
            ->count();
        $comment->save();

        return [
            'liked' => $liked,
            'likesCount' => (int) $comment->likes_count,
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array{page: int, perPage: int, total: int, totalPages: int}}
     */
    public function adminList(Request $request): array
    {
        $page = AdminPage::query($request, 20);
        $query = BlogComment::query()->with(['user:id,name,phone', 'post:id,title,slug']);
        $status = trim((string) $request->query('status', ''));
        if (in_array($status, [BlogComment::STATUS_APPROVED, BlogComment::STATUS_HIDDEN], true)) {
            $query->where('status', $status);
        }
        $paginator = $query->orderByDesc('id')->paginate($page['perPage'], ['*'], 'page', $page['page']);

        return [
            'items' => collect($paginator->items())->map(fn (BlogComment $comment) => $this->toAdmin($comment))->values()->all(),
            'meta' => AdminPage::meta($paginator),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function setStatus(BlogComment $comment, string $status): array
    {
        if (! in_array($status, [BlogComment::STATUS_APPROVED, BlogComment::STATUS_HIDDEN], true)) {
            throw new ApiException('وضعیت نامعتبر است.', 422);
        }
        $wasApproved = $comment->status === BlogComment::STATUS_APPROVED;
        $comment->status = $status;
        $comment->save();
        $nowApproved = $status === BlogComment::STATUS_APPROVED;
        if ($wasApproved && ! $nowApproved) {
            BlogPost::query()->where('id', $comment->post_id)->where('comments_count', '>', 0)->decrement('comments_count');
        }
        if (! $wasApproved && $nowApproved) {
            BlogPost::query()->where('id', $comment->post_id)->increment('comments_count');
        }

        return $this->toAdmin($comment->fresh(['user:id,name,phone', 'post:id,title,slug']) ?? $comment);
    }

    /**
     * @param  list<int>  $likedIds
     * @return array<string, mixed>
     */
    public function toPublic(BlogComment $comment, array $likedIds): array
    {
        $replies = $comment->relationLoaded('replies')
            ? $comment->replies->map(fn (BlogComment $reply) => $this->toPublic($reply, $likedIds))->values()->all()
            : [];

        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'status' => $comment->status,
            'parentId' => $comment->parent_id,
            'likesCount' => (int) $comment->likes_count,
            'liked' => in_array((int) $comment->id, $likedIds, true),
            'createdAt' => $comment->created_at?->toIso8601String(),
            'author' => [
                'name' => $this->displayName($comment),
            ],
            'replies' => $replies,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdmin(BlogComment $comment): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'status' => $comment->status,
            'parentId' => $comment->parent_id,
            'likesCount' => (int) $comment->likes_count,
            'createdAt' => $comment->created_at?->toIso8601String(),
            'authorName' => $this->displayName($comment),
            'postId' => $comment->post_id,
            'postTitle' => $comment->post?->title,
            'postSlug' => $comment->post?->slug,
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function likedCommentIds(?string $userId, array $ids): array
    {
        if (! $userId || $ids === []) {
            return [];
        }

        return BlogReaction::query()
            ->where('user_id', $userId)
            ->where('reactable_type', BlogReaction::TYPE_COMMENT)
            ->whereIn('reactable_id', $ids)
            ->pluck('reactable_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function displayName(BlogComment $comment): string
    {
        $name = trim((string) ($comment->user?->name ?? ''));
        if ($name !== '') {
            return $name;
        }
        $phone = (string) ($comment->user?->phone ?? '');
        if (strlen($phone) >= 4) {
            return 'کاربر ‎'.substr($phone, -4);
        }

        return 'کاربر شارژینو';
    }
}
