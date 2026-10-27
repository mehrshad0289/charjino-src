<?php

namespace App\Modules\Catalog;

use App\Exceptions\ApiException;
use App\Models\BlogReaction;
use App\Models\Product;
use App\Models\ProductComment;
use App\Support\AdminPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductCommentService
{
    /**
     * @return array<string, mixed>
     */
    public function publicList(string $handle, Request $request, ?string $viewerId = null): array
    {
        $product = $this->storefrontProduct($handle);
        $page = AdminPage::query($request, 20);
        $paginator = ProductComment::query()
            ->approved()
            ->where('product_id', $product->id)
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
            'items' => collect($paginator->items())->map(fn (ProductComment $comment) => $this->toPublic($comment, $liked))->values()->all(),
            'meta' => AdminPage::meta($paginator),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $handle, string $userId, string $body, ?int $parentId): array
    {
        $product = $this->storefrontProduct($handle);
        $text = trim($body);
        if (mb_strlen($text) < 3) {
            throw new ApiException('متن نظر خیلی کوتاه است.', 422);
        }
        if (mb_strlen($text) > 1000) {
            throw new ApiException('متن نظر حداکثر ۱۰۰۰ کاراکتر است.', 422);
        }
        $parent = null;
        if ($parentId) {
            $parent = ProductComment::query()
                ->approved()
                ->where('product_id', $product->id)
                ->where('id', $parentId)
                ->first();
            if (! $parent || ! $parent->isRoot()) {
                throw new ApiException('پاسخ فقط روی نظر اصلی مجاز است.', 422);
            }
        }
        $comment = ProductComment::query()->create([
            'product_id' => $product->id,
            'user_id' => $userId,
            'parent_id' => $parent?->id,
            'body' => $text,
            'status' => ProductComment::STATUS_PENDING,
        ]);
        $comment->load('user:id,name,phone');

        return $this->toPublic($comment, []);
    }

    /**
     * @return array<string, mixed>
     */
    public function toggleLike(ProductComment $comment, string $userId): array
    {
        if ($comment->status !== ProductComment::STATUS_APPROVED) {
            throw new ApiException('این نظر پیدا نشد.', 404, 'NOT_FOUND');
        }
        $liked = $this->toggleReaction((int) $comment->id, $userId);
        $comment->likes_count = BlogReaction::query()
            ->where('reactable_type', BlogReaction::TYPE_PRODUCT_COMMENT)
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
        $query = ProductComment::query()->with(['user:id,name,phone', 'product:id,title,handle']);
        $status = trim((string) $request->query('status', ''));
        if (in_array($status, ProductComment::STATUSES, true)) {
            $query->where('status', $status);
        }
        $paginator = $query->orderByDesc('id')->paginate($page['perPage'], ['*'], 'page', $page['page']);

        return [
            'items' => collect($paginator->items())->map(fn (ProductComment $comment) => $this->toAdmin($comment))->values()->all(),
            'meta' => AdminPage::meta($paginator),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function setStatus(ProductComment $comment, string $status): array
    {
        if (! in_array($status, [ProductComment::STATUS_APPROVED, ProductComment::STATUS_HIDDEN], true)) {
            throw new ApiException('وضعیت نامعتبر است.', 422);
        }
        $wasApproved = $comment->status === ProductComment::STATUS_APPROVED;
        $comment->status = $status;
        $comment->save();
        $nowApproved = $status === ProductComment::STATUS_APPROVED;
        if ($wasApproved && ! $nowApproved) {
            Product::query()->where('id', $comment->product_id)->where('comments_count', '>', 0)->decrement('comments_count');
        }
        if (! $wasApproved && $nowApproved) {
            Product::query()->where('id', $comment->product_id)->increment('comments_count');
        }

        return $this->toAdmin($comment->fresh(['user:id,name,phone', 'product:id,title,handle']) ?? $comment);
    }

    /**
     * @param  list<int>  $likedIds
     * @return array<string, mixed>
     */
    public function toPublic(ProductComment $comment, array $likedIds): array
    {
        $replies = $comment->relationLoaded('replies')
            ? $comment->replies->map(fn (ProductComment $reply) => $this->toPublic($reply, $likedIds))->values()->all()
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
    public function toAdmin(ProductComment $comment): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'status' => $comment->status,
            'parentId' => $comment->parent_id,
            'likesCount' => (int) $comment->likes_count,
            'createdAt' => $comment->created_at?->toIso8601String(),
            'authorName' => $this->displayName($comment),
            'productId' => $comment->product_id,
            'productTitle' => $comment->product?->title,
            'productHandle' => $comment->product?->handle,
        ];
    }

    public function storefrontProduct(string $handle): Product
    {
        $normalized = mb_strtolower($handle);
        $found = Product::query()
            ->with('catalogGroup')
            ->where('status', 'active')
            ->notStorefrontExcluded()
            ->where(function ($query) use ($normalized) {
                $query->whereRaw('LOWER(handle) = ?', [$normalized])
                    ->orWhereRaw('LOWER(code) = ?', [$normalized]);
            })
            ->first();
        $lifecycle = app(CatalogUrlLifecycle::class);
        if (! $found || $found->isStorefrontExcluded()) {
            throw new ApiException('محصول پیدا نشد.', 404, 'NOT_FOUND');
        }
        if (! $found->storefrontAvailable() && ! $lifecycle->isTemporaryUnavailable($found)) {
            throw new ApiException('محصول پیدا نشد.', 404, 'NOT_FOUND');
        }
        $group = $found->catalogGroup;
        if ($group && ! $group->visible) {
            throw new ApiException('محصول پیدا نشد.', 404, 'NOT_FOUND');
        }

        return $found;
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
            ->where('reactable_type', BlogReaction::TYPE_PRODUCT_COMMENT)
            ->whereIn('reactable_id', $ids)
            ->pluck('reactable_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function toggleReaction(int $id, string $userId): bool
    {
        return DB::transaction(function () use ($id, $userId) {
            $existing = BlogReaction::query()
                ->where('user_id', $userId)
                ->where('reactable_type', BlogReaction::TYPE_PRODUCT_COMMENT)
                ->where('reactable_id', $id)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $existing->delete();

                return false;
            }
            BlogReaction::query()->create([
                'user_id' => $userId,
                'reactable_type' => BlogReaction::TYPE_PRODUCT_COMMENT,
                'reactable_id' => $id,
                'vote' => BlogReaction::VOTE_LIKE,
            ]);

            return true;
        });
    }

    private function displayName(ProductComment $comment): string
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
