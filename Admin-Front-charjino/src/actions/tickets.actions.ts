'use server';

import { revalidatePath } from 'next/cache';
import { ApiError, serverFetch } from '@/lib/server-fetcher';
import { apiPaths } from '@/lib/api-paths';
import type { AdminTicket, AdminTicketPage, TicketStatus } from '@/types/tickets';

function toActionMessage(error: unknown) {
  if (error instanceof ApiError && error.message) return error.message;
  if (error instanceof Error && error.message) return error.message;
  return 'درخواست انجام نشد';
}

function withQuery(path: string, params: Record<string, string | number | undefined | null>) {
  const qs = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '' || value === 'all') continue;
    qs.set(key, String(value));
  }
  const suffix = qs.toString();
  return suffix ? `${path}?${suffix}` : path;
}

export async function listAdminTickets(params: {
  status?: string;
  q?: string;
  page?: number;
  perPage?: number;
}) {
  try {
    const data = await serverFetch<AdminTicketPage>(
      withQuery(apiPaths.adminTickets, {
        status: params.status,
        q: params.q,
        page: params.page,
        perPage: params.perPage,
      }),
    );
    return { ok: true as const, items: data.items, meta: data.meta };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function getAdminTicket(id: string) {
  const key = id.trim();
  if (!key) {
    return { ok: false as const, message: 'تیکت پیدا نشد.', notFound: true as const };
  }
  try {
    const data = await serverFetch<AdminTicket>(apiPaths.adminTicket(key));
    return { ok: true as const, ticket: data };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 404) {
      return { ok: false as const, message: toActionMessage(error), notFound: true as const };
    }
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function replyAdminTicket(id: string, body: string) {
  const key = id.trim();
  if (!key) {
    return { ok: false as const, message: 'تیکت پیدا نشد.' };
  }
  try {
    const data = await serverFetch<AdminTicket>(apiPaths.adminTicketMessages(key), {
      method: 'POST',
      body: JSON.stringify({ body }),
    });
    revalidatePath('/support');
    revalidatePath(`/support/${key}`);
    return { ok: true as const, ticket: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function setAdminTicketStatus(id: string, status: TicketStatus) {
  const key = id.trim();
  if (!key) {
    return { ok: false as const, message: 'تیکت پیدا نشد.' };
  }
  try {
    const data = await serverFetch<AdminTicket>(apiPaths.adminTicketStatus(key), {
      method: 'POST',
      body: JSON.stringify({ status }),
    });
    revalidatePath('/support');
    revalidatePath(`/support/${key}`);
    return { ok: true as const, ticket: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}
