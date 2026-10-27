import { getBackendUrl } from '@/lib/env';
import type { ApiResponse } from '@/types/api';
import type { RefreshResponse } from '@/types/auth';

export class RefreshAuthError extends Error {
  readonly statusCode = 401;

  constructor(message = 'تمدید نشست انجام نشد') {
    super(message);
    this.name = 'RefreshAuthError';
  }
}

export async function requestNewAccessToken(refreshToken: string): Promise<string> {
  const response = await fetch(`${getBackendUrl()}/auth/refresh`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ refresh_token: refreshToken }),
    cache: 'no-store',
  });

  const payload = (await response.json().catch(() => undefined)) as
    | ApiResponse<RefreshResponse>
    | RefreshResponse
    | undefined;

  const wrapped = payload && 'data' in payload ? payload.data : undefined;
  const accessToken =
    wrapped?.access_token ??
    (payload && 'access_token' in payload ? payload.access_token : undefined);

  if (response.status === 401) {
    throw new RefreshAuthError();
  }

  if (!response.ok || !accessToken) {
    throw new Error('تمدید نشست انجام نشد');
  }

  return accessToken;
}
