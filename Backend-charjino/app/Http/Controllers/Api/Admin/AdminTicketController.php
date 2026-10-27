<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Modules\Support\TicketService;
use App\Support\AdminPage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTicketController extends Controller
{
    public function __construct(private readonly TicketService $tickets) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->tickets->listAdmin($request);

        return ApiResponse::ok([
            'items' => collect($paginator->items())->map(fn (Ticket $ticket) => $ticket->toAdmin())->all(),
            'meta' => AdminPage::meta($paginator),
        ]);
    }

    public function show(string $ticket): JsonResponse
    {
        return ApiResponse::ok($this->tickets->findAdmin($ticket)->toAdmin(true));
    }

    public function reply(Request $request, string $ticket): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);
        $model = $this->tickets->findAdmin($ticket);
        $updated = $this->tickets->replyAsStaff($model, $data['body']);

        return ApiResponse::ok($updated->toAdmin(true), 'پاسخ ثبت شد.');
    }

    public function setStatus(Request $request, string $ticket): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'max:16'],
        ]);
        $model = $this->tickets->findAdmin($ticket);
        $updated = $this->tickets->setStatus($model, $data['status']);

        return ApiResponse::ok($updated->toAdmin(true), 'وضعیت تیکت به‌روز شد.');
    }
}
