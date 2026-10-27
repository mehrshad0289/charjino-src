import { getAccessToken } from '@/lib/auth-cookies';
import { getBackendUrl } from '@/lib/env';
import type { ApiErrorResponse, ApiResponse } from '@/types/api';

export class ApiError extends Error {
  readonly statusCode: number;
  readonly errorCode?: string;
  readonly data?: unknown;

  constructor(message: string, statusCode: number, errorCode?: string, data?: unknown) {
    super(message);
    this.name = 'ApiError';
    this.statusCode = statusCode;
    this.errorCode = errorCode;
    this.data = data;
  }
}

export class TokenExpiredError extends ApiError {
  readonly isTokenExpiredError = true as const;

  constructor(message: string) {
    super(message, 401);
    this.name = 'TokenExpiredError';
  }
}

function resolveBackendUrl(path: string) {
  return `${getBackendUrl()}/${path.replace(/^\//, '')}`;
}

function toErrorCode(payload: unknown): string | undefined {
  if (!payload || typeof payload !== 'object') return undefined;
  const error = 'error' in payload ? payload.error : undefined;
  if (!error || typeof error !== 'object') return undefined;
  const code = 'code' in error ? error.code : undefined;
  return typeof code === 'string' && code.trim() ? code.trim().toUpperCase() : undefined;
}

function toErrorMessage(payload: unknown, fallback: string) {
  if (!payload || typeof payload !== 'object') return fallback;

  const message = 'message' in payload ? payload.message : undefined;

  if (typeof message === 'string' && message.trim()) {
    return message;
  }

  if (Array.isArray(message)) {
    const parts = message.filter(
      (item): item is string => typeof item === 'string' && item.trim().length > 0,
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
  if (
    cause?.code === 'ECONNREFUSED' ||
    cause?.code === 'ENOTFOUND' ||
    cause?.code === 'ETIMEDOUT'
  ) {
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

function isMutationMethod(method?: string) {
  const verb = (method ?? 'GET').toUpperCase();
  return verb !== 'GET' && verb !== 'HEAD';
}

export async function serverFetch<T>(
  path: string,
  init: ServerFetchInit = {},
): Promise<T> {
  const { auth = true, headers: initHeaders, cache, next, ...requestInit } = init;
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

  const allowFetchCache =
    !auth &&
    !isMutationMethod(requestInit.method) &&
    Boolean(next && (next.revalidate !== undefined || (next.tags && next.tags.length > 0)));

  let response: Response;
  try {
    response = await fetch(resolveBackendUrl(path), {
      ...requestInit,
      headers,
      ...(allowFetchCache ? { next, ...(cache ? { cache } : {}) } : { cache: 'no-store' }),
    });
  } catch (error) {
    throw new ApiError(toFetchFailureMessage(error), 503);
  }

  const payload = await readPayload(response);
  const apiPayload = payload as ApiResponse<T> | ApiErrorResponse | undefined;
  const errorMessage = toErrorMessage(payload, 'درخواست انجام نشد');
  const errorCode = toErrorCode(payload);

  if (response.status === 401) {
    throw new ApiError(errorMessage || 'احراز هویت نامعتبر است', 401, errorCode);
  }

  if (!response.ok || apiPayload?.success === false) {
    const payloadData =
      apiPayload && typeof apiPayload === 'object' && 'data' in apiPayload ? apiPayload.data : undefined;
    throw new ApiError(errorMessage, response.status, errorCode, payloadData);
  }

  return (apiPayload?.data ?? payload) as T;
}
