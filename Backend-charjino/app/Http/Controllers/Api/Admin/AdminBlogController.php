<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\BlogPostRevision;
use App\Models\BlogTag;
use App\Modules\Blog\BlogCommentService;
use App\Modules\Blog\BlogImageService;
use App\Modules\Blog\BlogService;
use App\Support\ApiResponse;
use App\Support\AuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBlogController extends Controller
{
    public function categories(BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->adminCategories());
    }

    public function storeCategory(Request $request, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->createCategory($this->validatedCategory($request, false)), 'دسته ذخیره شد.', 201);
    }

    public function updateCategory(Request $request, BlogCategory $category, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->updateCategory($category, $this->validatedCategory($request, true)), 'دسته به‌روز شد.');
    }

    public function destroyCategory(BlogCategory $category, BlogService $blog): JsonResponse
    {
        $blog->deleteCategory($category);

        return ApiResponse::ok(null, 'دسته حذف شد.');
    }

    public function tags(BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->adminTags());
    }

    public function storeTag(Request $request, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->createTag($this->validatedTag($request, false)), 'تگ ذخیره شد.', 201);
    }

    public function updateTag(Request $request, BlogTag $tag, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->updateTag($tag, $this->validatedTag($request, true)), 'تگ به‌روز شد.');
    }

    public function destroyTag(BlogTag $tag, BlogService $blog): JsonResponse
    {
        $blog->deleteTag($tag);

        return ApiResponse::ok(null, 'تگ حذف شد.');
    }

    public function posts(Request $request, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->adminPosts($request));
    }

    public function show(BlogPost $post, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->adminShow($post));
    }

    public function store(Request $request, BlogService $blog): JsonResponse
    {
        $post = $blog->createPost($this->validatedPost($request, false), AuthUser::id($request));

        return ApiResponse::ok($post, 'مطلب ذخیره شد.', 201);
    }

    public function update(Request $request, BlogPost $post, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->updatePost($post, $this->validatedPost($request, true)), 'مطلب به‌روز شد.');
    }

    public function destroy(BlogPost $post, BlogService $blog): JsonResponse
    {
        $blog->deletePost($post);

        return ApiResponse::ok(null, 'مطلب حذف شد.');
    }

    public function publish(Request $request, BlogPost $post, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->publish($post, AuthUser::id($request), $request->all()), 'مطلب منتشر شد.');
    }

    public function unpublish(BlogPost $post, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->unpublish($post), 'مطلب از انتشار خارج شد.');
    }

    public function revisions(BlogPost $post, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok(['items' => $blog->revisions($post)]);
    }

    public function restoreRevision(BlogPost $post, BlogPostRevision $revision, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->restoreRevision($post, $revision), 'نسخه بازگردانی شد.');
    }

    public function slugAvailable(Request $request, BlogService $blog): JsonResponse
    {
        $slug = trim((string) $request->query('slug', ''));
        $ignore = (int) $request->query('ignoreId', 0);

        return ApiResponse::ok([
            'slug' => $slug,
            'available' => $slug !== '' && $blog->slugAvailable($slug, $ignore > 0 ? $ignore : null),
        ]);
    }

    public function linkSearch(Request $request, BlogService $blog): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return ApiResponse::ok(['posts' => [], 'products' => []]);
        }

        return ApiResponse::ok($blog->linkSearch($q));
    }

    public function upload(Request $request, BlogImageService $images): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'image', 'max:4096', 'mimes:jpeg,jpg,png,webp'],
            'alt' => ['nullable', 'string', 'max:180'],
            'role' => ['nullable', 'in:inline,cover'],
        ]);
        $stored = $images->store(
            $request->file('file'),
            (string) ($data['alt'] ?? ''),
            ($data['role'] ?? 'inline') === 'cover',
        );

        return ApiResponse::ok($stored, 'تصویر ذخیره شد.');
    }

    public function comments(Request $request, BlogCommentService $comments): JsonResponse
    {
        return ApiResponse::ok($comments->adminList($request));
    }

    public function updateComment(Request $request, BlogComment $comment, BlogCommentService $comments): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,hidden'],
        ]);

        return ApiResponse::ok($comments->setStatus($comment, (string) $data['status']), 'وضعیت نظر به‌روز شد.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPost(Request $request, bool $partial): array
    {
        if ((int) $request->input('categoryId', $request->input('category_id', 0)) === 0) {
            $request->merge(['categoryId' => null]);
        }
        $autosave = $request->boolean('autosave');
        $publishing = $request->boolean('publish')
            || in_array($request->input('status'), ['published', 'scheduled'], true);
        if ($autosave && ! $publishing) {
            $title = $partial ? ['sometimes', 'required', 'string', 'min:3', 'max:100'] : ['required', 'string', 'min:3', 'max:100'];
            $content = ['nullable', 'string'];
        } else {
            $title = $partial ? ['sometimes', 'required', 'string', 'min:10', 'max:100'] : ['required', 'string', 'min:10', 'max:100'];
            $content = $partial ? ['sometimes', 'required', 'string', 'min:40'] : ['required', 'string', 'min:40'];
        }
        $category = $publishing
            ? ['required', 'integer', 'exists:blog_categories,id']
            : ['nullable', 'integer', 'exists:blog_categories,id'];

        return $request->validate([
            'title' => $title,
            'slug' => ['nullable', 'string', 'max:80'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => $content,
            'cover' => ['nullable', 'string', 'max:2048'],
            'coverAlt' => ['nullable', 'string', 'max:180'],
            'coverWidth' => ['nullable', 'integer', 'min:1', 'max:8000'],
            'coverHeight' => ['nullable', 'integer', 'min:1', 'max:8000'],
            'categoryId' => $category,
            'userId' => ['nullable', 'string', 'max:36'],
            'publish' => ['nullable', 'boolean'],
            'autosave' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:draft,published,scheduled'],
            'publishedAt' => ['nullable', 'date'],
            'metaTitle' => ['nullable', 'string', 'max:80'],
            'metaDescription' => ['nullable', 'string', 'max:180'],
            'canonicalUrl' => ['nullable', 'string', 'max:2048'],
            'noindex' => ['nullable', 'boolean'],
            'focusKeyword' => ['nullable', 'string', 'max:80'],
            'ogTitle' => ['nullable', 'string', 'max:80'],
            'ogDescription' => ['nullable', 'string', 'max:180'],
            'ogImage' => ['nullable', 'string', 'max:2048'],
            'twitterTitle' => ['nullable', 'string', 'max:80'],
            'twitterDescription' => ['nullable', 'string', 'max:180'],
            'twitterImage' => ['nullable', 'string', 'max:2048'],
            'secondaryKeywords' => ['nullable'],
            'faq' => ['nullable'],
            'faq.*.question' => ['nullable', 'string', 'max:180'],
            'faq.*.answer' => ['nullable', 'string', 'max:1000'],
            'tagIds' => ['nullable', 'array'],
            'tagIds.*' => ['integer', 'exists:blog_tags,id'],
            'productIds' => ['nullable', 'array'],
            'productIds.*' => ['integer', 'exists:products,id'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedCategory(Request $request, bool $partial): array
    {
        return $request->validate([
            'title' => $partial ? ['sometimes', 'required', 'string', 'min:2', 'max:80'] : ['required', 'string', 'min:2', 'max:80'],
            'slug' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'bodyHtml' => ['nullable', 'string'],
            'metaTitle' => ['nullable', 'string', 'max:80'],
            'metaDescription' => ['nullable', 'string', 'max:180'],
            'noindex' => ['nullable', 'boolean'],
            'image' => ['nullable', 'string', 'max:2048'],
            'imageAlt' => ['nullable', 'string', 'max:180'],
            'parentId' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedTag(Request $request, bool $partial): array
    {
        return $request->validate([
            'title' => $partial ? ['sometimes', 'required', 'string', 'min:2', 'max:80'] : ['required', 'string', 'min:2', 'max:80'],
            'slug' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'bodyHtml' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:2048'],
            'imageAlt' => ['nullable', 'string', 'max:180'],
            'metaTitle' => ['nullable', 'string', 'max:80'],
            'metaDescription' => ['nullable', 'string', 'max:180'],
            'forceNoindex' => ['nullable', 'boolean'],
            'noindex' => ['nullable', 'boolean'],
        ]);
    }
}
