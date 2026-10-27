'use server';

import { revalidatePath } from 'next/cache';
import { ApiError, serverFetch } from '@/lib/server-fetcher';
import { apiPaths } from '@/lib/api-paths';
import type { SupportTicket, TicketPage } from '@/types/profile';

function toMessage(error: unknown) {
  if (error instanceof ApiError && error.message) return error.message;
  if (error instanceof Error && error.message) return error.message;
  return 'درخواست انجام نشد';
}

export async function listMyTickets(page = 1) {
  try {
    const data = await serverFetch<TicketPage>(apiPaths.ticketsQuery(page));
    return { ok: true as const, data };
  } catch (error) {
    const message = toMessage(error);
    if (error instanceof ApiError && error.statusCode === 401) {
      return { ok: false as const, needAuth: true as const, message };
    }
    return { ok: false as const, message };
  }
}

export async function getMyTicket(id: string) {
  const key = id.trim();
  if (!key) {
    return { ok: false as const, message: 'تیکت پیدا نشد.', notFound: true as const };
  }
  try {
    const data = await serverFetch<SupportTicket>(apiPaths.ticket(key));
    return { ok: true as const, data };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 404) {
      return { ok: false as const, message: toMessage(error), notFound: true as const };
    }
    return { ok: false as const, message: toMessage(error) };
  }
}

export async function createTicket(input: {
  subject: string;
  body: string;
  category?: string;
  orderNumber?: string;
}) {
  try {
    const data = await serverFetch<SupportTicket>(apiPaths.tickets, {
      method: 'POST',
      body: JSON.stringify({
        subject: input.subject,
        body: input.body,
        category: input.category || 'other',
        orderNumber: input.orderNumber?.trim() || undefined,
      }),
    });
    revalidatePath('/profile/tickets');
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toMessage(error) };
  }
}

export async function replyToTicket(id: string, body: string) {
  const key = id.trim();
  if (!key) {
    return { ok: false as const, message: 'تیکت پیدا نشد.' };
  }
  try {
    const data = await serverFetch<SupportTicket>(apiPaths.ticketMessages(key), {
      method: 'POST',
      body: JSON.stringify({ body }),
    });
    revalidatePath('/profile/tickets');
    revalidatePath(`/profile/tickets/${key}`);
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toMessage(error) };
  }
}
