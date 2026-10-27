export type AuthUser = {
  id: string;
  email: string;
  role?: 'admin' | 'user';
};

export type SignInInput = {
  email: string;
  password: string;
  remember: boolean;
};

export type SignInResult = { ok: true; user: AuthUser } | { ok: false; message: string };

export type AdminLoginResponse = {
  access_token: string;
  refresh_token: string;
  user: AuthUser;
};

export type RefreshResponse = {
  access_token: string;
};

export type { ApiResponse } from '@/types/api';
