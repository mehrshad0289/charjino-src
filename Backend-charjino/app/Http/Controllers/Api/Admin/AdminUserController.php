<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminPage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->orderByDesc('created_at');
        $role = trim((string) $request->query('role', ''));
        if (in_array($role, ['admin', 'user'], true)) {
            $query->where('role', $role);
        }
        if ($request->query->has('page') || $request->query->has('perPage')) {
            $page = AdminPage::query($request, 10);
            $paginator = $query->paginate($page['perPage'], ['*'], 'page', $page['page']);

            return ApiResponse::ok([
                'items' => collect($paginator->items())->map(fn (User $user) => $this->row($user))->values()->all(),
                'meta' => AdminPage::meta($paginator),
            ]);
        }

        $items = $query->get()->map(fn (User $user) => $this->row($user))->values();

        return ApiResponse::ok(['items' => $items]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phoneNumber' => $user->phone,
            'role' => $user->role,
            'status' => $user->status,
            'createdAt' => $user->created_at?->toIso8601String(),
        ];
    }
}
