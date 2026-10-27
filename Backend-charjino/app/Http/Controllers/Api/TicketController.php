<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Support\TicketService;
use App\Support\ApiResponse;
use App\Support\AuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $tickets) {}

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('perPage', 20)));
        $paginator = $this->tickets->listForUser(AuthUser::id($request), $page, $perPage);

        return ApiResponse::ok([
            'items' => collect($paginator->items())->map(fn ($ticket) => $ticket->toCustomer())->all(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:4000'],
            'category' => ['nullable', 'string', 'max:32'],
            'orderNumber' => ['nullable', 'string', 'max:32'],
        ]);

        $ticket = $this->tickets->create(
            AuthUser::id($request),
            $data['subject'],
            $data['body'],
            $data['category'] ?? null,
            $data['orderNumber'] ?? null,
        );

        return ApiResponse::ok($ticket->toCustomer(true), 'تیکت ثبت شد.');
    }

    public function show(Request $request, string $ticket): JsonResponse
    {
        $model = $this->tickets->findOwned(AuthUser::id($request), $ticket);

        return ApiResponse::ok($model->toCustomer(true));
    }

    public function reply(Request $request, string $ticket): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);
        $model = $this->tickets->findOwned(AuthUser::id($request), $ticket);
        $updated = $this->tickets->replyAsUser($model, $data['body']);

        return ApiResponse::ok($updated->toCustomer(true), 'پیام ثبت شد.');
    }
}
