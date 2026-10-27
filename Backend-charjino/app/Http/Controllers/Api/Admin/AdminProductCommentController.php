<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductComment;
use App\Modules\Catalog\ProductCommentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductCommentController extends Controller
{
    public function index(Request $request, ProductCommentService $comments): JsonResponse
    {
        return ApiResponse::ok($comments->adminList($request));
    }

    public function update(Request $request, ProductComment $comment, ProductCommentService $comments): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,hidden'],
        ]);

        return ApiResponse::ok($comments->setStatus($comment, (string) $data['status']), 'وضعیت نظر به‌روز شد.');
    }
}
