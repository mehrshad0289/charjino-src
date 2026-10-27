<?php

namespace App\Modules\Support;

use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Support\AdminPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class TicketService
{
    /**
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function listForUser(string $userId, int $page, int $perPage): LengthAwarePaginator
    {
        return Ticket::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function listAdmin(Request $request): LengthAwarePaginator
    {
        $page = AdminPage::query($request);
        $query = Ticket::query()->with('user')->orderByDesc('updated_at');
        $status = trim((string) $request->query('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('number', 'like', $like)
                    ->orWhere('subject', 'like', $like)
                    ->orWhereHas('user', fn ($user) => $user->where('phone', 'like', $like));
            });
        }

        return $query->paginate($page['perPage'], ['*'], 'page', $page['page']);
    }

    public function findOwned(string $userId, string $idOrNumber): Ticket
    {
        $ticket = Ticket::query()
            ->where('user_id', $userId)
            ->where(function ($query) use ($idOrNumber) {
                $query->where('id', $idOrNumber)->orWhere('number', $idOrNumber);
            })
            ->first();
        if (! $ticket) {
            throw new ApiException('تیکت پیدا نشد.', 404, 'TICKET_NOT_FOUND');
        }

        return $ticket;
    }

    public function findAdmin(string $idOrNumber): Ticket
    {
        $ticket = Ticket::query()->where('id', $idOrNumber)->orWhere('number', $idOrNumber)->first();
        if (! $ticket) {
            throw new ApiException('تیکت پیدا نشد.', 404, 'TICKET_NOT_FOUND');
        }

        return $ticket;
    }

    public function create(string $userId, string $subject, string $body, ?string $category, ?string $orderNumber): Ticket
    {
        $subject = trim($subject);
        $body = trim($body);
        if (mb_strlen($subject) < 4) {
            throw new ApiException('موضوع تیکت کوتاه است.', 422, 'TICKET_SUBJECT_SHORT');
        }
        if (mb_strlen($body) < 8) {
            throw new ApiException('متن تیکت کوتاه است.', 422, 'TICKET_BODY_SHORT');
        }

        $cat = in_array($category, Ticket::CATEGORIES, true) ? $category : 'other';
        $order = $this->ownedOrderNumber($userId, $orderNumber);

        $ticket = Ticket::query()->create([
            'user_id' => $userId,
            'subject' => mb_substr($subject, 0, 160),
            'category' => $cat,
            'order_number' => $order,
            'status' => Ticket::STATUS_OPEN,
        ]);
        $ticket->messages()->create([
            'sender' => TicketMessage::SENDER_USER,
            'body' => $body,
        ]);

        return $ticket->fresh('messages') ?? $ticket;
    }

    public function replyAsUser(Ticket $ticket, string $body): Ticket
    {
        if ($ticket->status === Ticket::STATUS_CLOSED) {
            throw new ApiException('این تیکت بسته شده است.', 400, 'TICKET_CLOSED');
        }
        $text = trim($body);
        if (mb_strlen($text) < 2) {
            throw new ApiException('متن پیام کوتاه است.', 422, 'TICKET_BODY_SHORT');
        }
        $ticket->messages()->create([
            'sender' => TicketMessage::SENDER_USER,
            'body' => $text,
        ]);
        $ticket->status = Ticket::STATUS_OPEN;
        $ticket->save();

        return $ticket->fresh('messages') ?? $ticket;
    }

    public function replyAsStaff(Ticket $ticket, string $body): Ticket
    {
        if ($ticket->status === Ticket::STATUS_CLOSED) {
            throw new ApiException('این تیکت بسته شده است.', 400, 'TICKET_CLOSED');
        }
        $text = trim($body);
        if (mb_strlen($text) < 2) {
            throw new ApiException('متن پیام کوتاه است.', 422, 'TICKET_BODY_SHORT');
        }
        $ticket->messages()->create([
            'sender' => TicketMessage::SENDER_STAFF,
            'body' => $text,
        ]);
        $ticket->status = Ticket::STATUS_ANSWERED;
        $ticket->save();

        return $ticket->fresh('messages') ?? $ticket;
    }

    public function setStatus(Ticket $ticket, string $status): Ticket
    {
        if (! in_array($status, [Ticket::STATUS_OPEN, Ticket::STATUS_ANSWERED, Ticket::STATUS_CLOSED], true)) {
            throw new ApiException('وضعیت تیکت نامعتبر است.', 422, 'TICKET_STATUS_INVALID');
        }
        $ticket->status = $status;
        $ticket->save();

        return $ticket;
    }

    private function ownedOrderNumber(string $userId, ?string $orderNumber): ?string
    {
        $number = trim((string) $orderNumber);
        if ($number === '') {
            return null;
        }
        $order = Order::query()->where('user_id', $userId)->where('number', $number)->first();
        if (! $order) {
            throw new ApiException('این شماره سفارش برای این حساب نیست.', 422, 'ORDER_NOT_FOUND');
        }

        return $order->number;
    }
}
