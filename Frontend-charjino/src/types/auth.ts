/**
 * قرارداد Auth با بک‌اند (SCHEMA-DECISIONS Q6):
 * - JSON/API: phoneNumber
 * - ستون DB: phone
 * فرانت فقط phoneNumber می‌فرستد و می‌گیرد.
 */
export type AuthUser = {
  id: string;
  phoneNumber: string | null;
  name?: string | null;
  avatarUrl?: string | null;
};

export type VerifyOtpResponse = {
  access_token: string;
  refresh_token: string;
  user: AuthUser;
};

export type RefreshResponse = {
  access_token: string;
};

export type { ApiResponse } from '@/types/api';
