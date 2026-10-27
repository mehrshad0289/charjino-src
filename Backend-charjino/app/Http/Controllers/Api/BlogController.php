<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogComment;
use App\Modules\Blog\BlogCommentService;
use App\Modules\Blog\BlogService;
use App\Support\ApiResponse;
use App\Support\AuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function hub(Request $request, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->hub($request));
    }

    public function posts(Request $request, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->publicPosts($request));
    }

    public function categories(BlogService $blog): JsonResponse
    {
        return ApiResponse::ok(['items' => $blog->publicCategories()]);
    }

    public function category(string $slug, Request $request, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->publicCategory($slug, $request));
    }

    public function tag(string $slug, Request $request, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->publicTag($slug, $request));
    }

    public function author(string $slug, Request $request, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->publicAuthor($slug, $request));
    }

    public function sitemap(BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->sitemap());
    }

    public function show(string $slug, Request $request, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->publicShow($slug, AuthUser::idOrNull($request)));
    }

    public function preview(string $token, BlogService $blog): JsonResponse
    {
        return ApiResponse::ok($blog->publicPreview($token));
    }

    public function comments(
        string $slug,
        Request $request,
        BlogService $blog,
        BlogCommentService $comments,
    ): JsonResponse {
        $post = $blog->publishedBySlug($slug);

        return ApiResponse::ok($comments->publicList($post, $request, AuthUser::idOrNull($request)));
    }

    public function storeComment(
        string $slug,
        Request $request,
        BlogService $blog,
        BlogCommentService $comments,
    ): JsonResponse {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:1000'],
            'parentId' => ['nullable', 'integer'],
        ]);
        $post = $blog->publishedBySlug($slug);
        $item = $comments->create(
            $post,
            AuthUser::id($request),
            (string) $data['body'],
            isset($data['parentId']) ? (int) $data['parentId'] : null,
        );

        return ApiResponse::ok($item, 'نظر ثبت شد و پس از تأیید نمایش داده می‌شود.', 201);
    }

    public function likePost(string $slug, Request $request, BlogService $blog): JsonResponse
    {
        $post = $blog->publishedBySlug($slug);

        return ApiResponse::ok($blog->togglePostLike($post, AuthUser::id($request)));
    }

    public function likeComment(BlogComment $comment, Request $request, BlogCommentService $comments): JsonResponse
    {
        return ApiResponse::ok($comments->toggleLike($comment, AuthUser::id($request)));
    }
}
