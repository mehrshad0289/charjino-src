<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductComment;
use App\Modules\Catalog\ProductCommentService;
use App\Support\ApiResponse;
use App\Support\AuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCommentController extends Controller
{
    public function index(string $handle, Request $request, ProductCommentService $comments): JsonResponse
    {
        return ApiResponse::ok($comments->publicList($handle, $request, AuthUser::idOrNull($request)));
    }

    public function store(string $handle, Request $request, ProductCommentService $comments): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:1000'],
            'parentId' => ['nullable', 'integer'],
        ]);
        $item = $comments->create(
            $handle,
            AuthUser::id($request),
            (string) $data['body'],
            isset($data['parentId']) ? (int) $data['parentId'] : null,
        );

        return ApiResponse::ok($item, 'نظر ثبت شد و پس از تأیید نمایش داده می‌شود.', 201);
    }

    public function like(ProductComment $comment, Request $request, ProductCommentService $comments): JsonResponse
    {
        return ApiResponse::ok($comments->toggleLike($comment, AuthUser::id($request)));
    }
}
