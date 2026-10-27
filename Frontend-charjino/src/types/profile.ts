export type TicketStatus = 'open' | 'answered' | 'closed';

export type TicketCategory = 'order' | 'payment' | 'delivery' | 'account' | 'other';

export type TicketMessage = {
  id: number;
  sender: 'user' | 'staff';
  body: string;
  createdAt?: string | null;
};

export type SupportTicket = {
  id: string;
  number: string;
  subject: string;
  category?: TicketCategory | string;
  orderNumber?: string | null;
  status: TicketStatus | string;
  createdAt?: string | null;
  updatedAt?: string | null;
  messages?: TicketMessage[];
};

export type TicketPage = {
  items: SupportTicket[];
  meta: {
    page: number;
    perPage: number;
    total: number;
    totalPages: number;
  };
};

export type FavoriteProduct = {
  id: number;
  handle: string;
  title: string;
  thumbnail?: string | null;
  minPrice?: number | null;
};

export type FavoriteItem = {
  id: number;
  productId: number;
  product: FavoriteProduct | null;
};

export type FavoritePage = {
  items: FavoriteItem[];
  meta: {
    page: number;
    perPage: number;
    total: number;
    totalPages: number;
  };
};

export type FavoriteStatus = {
  productId: number;
  favorited: boolean;
};
