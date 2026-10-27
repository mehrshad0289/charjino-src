export type TicketStatus = 'open' | 'answered' | 'closed';

export type TicketRequester = {
  name: string;
  phone: string;
  avatar: string;
};

export type SupportTicket = {
  id: string;
  number: string;
  subject: string;
  requester: TicketRequester;
  createdLabel: string;
  headerTime?: string;
  createdAt: string;
  status: TicketStatus;
  category: string;
  orderNumber?: string | null;
};

export type TicketMessage = {
  id: string;
  authorName: string;
  authorEmail?: string;
  avatar: string;
  role: 'customer' | 'agent';
  timeLabel: string;
  body: string;
};

export type SupportTabId = 'all' | 'open' | 'answered' | 'closed';
