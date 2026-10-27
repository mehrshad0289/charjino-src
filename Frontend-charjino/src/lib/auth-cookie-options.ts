export const ACCESS_TOKEN_MAX_AGE = 30 * 60;
export const REFRESH_TOKEN_MAX_AGE = 5 * 24 * 60 * 60;

export const AUTH_COOKIE_BASE_OPTIONS = {
  httpOnly: true,
  secure: process.env.NODE_ENV === 'production',
  sameSite: 'lax' as const,
  path: '/',
};
