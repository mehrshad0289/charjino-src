export type AdminCoupon = {
  id: number;
  name: string;
  code: string;
  type: 'amount' | 'percentage';
  amount?: number | null;
  percentage?: number | null;
  maxPercentageAmount?: number | null;
  minOrderToman: number;
  usageLimitPerUser: number;
  maxUses?: number | null;
  isActive: boolean;
  expiredAt?: string | null;
  description?: string | null;
  usedCount: number;
  createdAt?: string | null;
};

export type AdminPageMeta = {
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
};
