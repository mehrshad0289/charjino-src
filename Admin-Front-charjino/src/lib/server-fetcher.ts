import { getAccessToken } from '@/lib/auth-cookies';
import { getBackendUrl } from '@/lib/env';
import type { ApiErrorResponse, ApiResponse } from '@/types/api';

export class ApiError extends Error {
  readonly statusCode: number;

  constructor(message: string, statusCode: number) {
    super(message);
    this.name = 'ApiError';
    this.statusCode = statusCode;
  }
}

function resolveBackendUrl(path: string) {
  return `${getBackendUrl()}/${path.replace(/^\//, '')}`;
}

function toErrorMessage(payload: unknown, fallback: string) {
  if (!payload || typeof payload !== 'object') return fallback;

  const message = 'message' in payload ? payload.message : undefined;

  if (typeof message === 'string' && message.trim()) {
    return message;
  }

  if (Array.isArray(message)) {
    const parts = message.filter(
      (item): item is string => typeof item === 'string' && item.trim().length > 0
    );
    if (parts.length > 0) return parts.join('، ');
  }

  return fallback;
}

function toFetchFailureMessage(error: unknown) {
  const cause =
    error && typeof error === 'object' && 'cause' in error
      ? (error.cause as { code?: string } | undefined)
      : undefined;
  if (cause?.code === 'ECONNREFUSED' || cause?.code === 'ENOTFOUND' || cause?.code === 'ETIMEDOUT') {
    return 'اتصال به سرور برقرار نشد';
  }
  if (error instanceof Error && error.message.toLowerCase().includes('fetch failed')) {
    return 'اتصال به سرور برقرار نشد';
  }
  if (error instanceof Error && error.message.trim()) return error.message;
  return 'درخواست انجام نشد';
}

async function readPayload(response: Response): Promise<unknown> {
  const text = await response.text();
  if (!text) return undefined;

  try {
    return JSON.parse(text) as unknown;
  } catch {
    return undefined;
  }
}

type ServerFetchInit = RequestInit & { auth?: boolean };

export async function serverFetch<T>(path: string, init: ServerFetchInit = {}): Promise<T> {
  const { auth = true, headers: initHeaders, ...requestInit } = init;
  const headers = new Headers(initHeaders);

  if (auth) {
    const accessToken = await getAccessToken();
    if (accessToken && !headers.has('Authorization')) {
      headers.set('Authorization', `Bearer ${accessToken}`);
    }
  }

  if (requestInit.body && !headers.has('Content-Type')) {
    if (!(requestInit.body instanceof FormData)) {
      headers.set('Content-Type', 'application/json');
    }
  }

  let response: Response;
  try {
    response = await fetch(resolveBackendUrl(path), {
      ...requestInit,
      headers,
      cache: 'no-store'
    });
  } catch (error) {
    throw new ApiError(toFetchFailureMessage(error), 503);
  }

  const payload = await readPayload(response);
  const apiPayload = payload as ApiResponse<T> | ApiErrorResponse | undefined;
  const errorMessage = toErrorMessage(payload, 'درخواست انجام نشد');

  if (response.status === 401) {
    throw new ApiError(errorMessage || 'احراز هویت نامعتبر است', 401);
  }

  if (!response.ok || apiPayload?.success === false) {
    throw new ApiError(errorMessage, response.status);
  }

  return (apiPayload?.data ?? payload) as T;
}
