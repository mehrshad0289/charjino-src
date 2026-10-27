export type TicketStatus = 'open' | 'answered' | 'closed';

export type TicketCategory = 'order' | 'payment' | 'delivery' | 'account' | 'other';

export type AdminTicketMessage = {
  id: number;
  sender: 'user' | 'staff';
  body: string;
  createdAt?: string | null;
};

export type AdminTicket = {
  id: string;
  number: string;
  subject: string;
  category?: TicketCategory | string;
  orderNumber?: string | null;
  status: TicketStatus | string;
  createdAt?: string | null;
  updatedAt?: string | null;
  userId?: number;
  userPhoneNumber?: string | null;
  userName?: string | null;
  messages?: AdminTicketMessage[];
};

export type AdminTicketPage = {
  items: AdminTicket[];
  meta: {
    page: number;
    perPage: number;
    total: number;
    totalPages: number;
  };
};
