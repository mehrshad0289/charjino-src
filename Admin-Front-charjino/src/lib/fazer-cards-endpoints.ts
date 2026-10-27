export type FcMethod = 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE';

export type FcEndpoint = {
  method: FcMethod;
  path: string;
  label: string;
  paid?: boolean;
  invoice?: boolean;
  dangerous?: boolean;
};

export const FC_ENDPOINT_GROUPS: { id: string; title: string; items: FcEndpoint[] }[] = [
  {
    id: 'account',
    title: 'حساب',
    items: [
      { method: 'GET', path: 'me', label: 'me' },
      { method: 'GET', path: 'balance', label: 'balance' }
    ]
  },
  {
    id: 'ledger',
    title: 'دفترکل',
    items: [
      { method: 'GET', path: 'transactions', label: 'transactions' },
      { method: 'GET', path: 'transactions/{transactionId}', label: 'transaction' }
    ]
  },
  {
    id: 'orders',
    title: 'سفارش‌ها',
    items: [
      { method: 'GET', path: 'orders', label: 'orders' },
      { method: 'GET', path: 'orders/{orderId}', label: 'order' }
    ]
  },
  {
    id: 'subscription',
    title: 'اشتراک',
    items: [
      { method: 'GET', path: 'subscription', label: 'وضعیت پلن' },
      { method: 'GET', path: 'subscription/plans', label: 'plans' },
      { method: 'GET', path: 'subscription/activation-quote', label: 'activation-quote' },
      { method: 'POST', path: 'subscription/activate-from-balance', label: 'activate', paid: true },
      { method: 'PATCH', path: 'subscription/auto-renew', label: 'auto-renew', paid: true },
      { method: 'PATCH', path: 'subscription/plan', label: 'تغییر پلن', paid: true }
    ]
  },
  {
    id: 'payments',
    title: 'پرداخت والت',
    items: [
      { method: 'GET', path: 'payments/methods', label: 'methods' },
      { method: 'POST', path: 'payments/create', label: 'create invoice', invoice: true },
      { method: 'GET', path: 'payments/{paymentId}', label: 'payment' },
      { method: 'POST', path: 'payments/{paymentId}/verify', label: 'verify' }
    ]
  },
  {
    id: 'topups',
    title: 'شارژ بازی',
    items: [
      { method: 'GET', path: 'topups', label: 'topups' },
      { method: 'GET', path: 'topups/offers', label: 'offers' },
      { method: 'GET', path: 'topups/validate-id', label: 'validate catalog' },
      { method: 'POST', path: 'topups/validate-id', label: 'validate-id' },
      { method: 'POST', path: 'topups/order', label: 'order', paid: true }
    ]
  },
  {
    id: 'giftcards',
    title: 'گیفت کارت',
    items: [
      { method: 'GET', path: 'giftcards', label: 'giftcards' },
      { method: 'GET', path: 'giftcards/cards', label: 'cards' },
      { method: 'POST', path: 'giftcards/order', label: 'order', paid: true }
    ]
  },
  {
    id: 'gamekeys',
    title: 'کلید بازی',
    items: [
      { method: 'GET', path: 'gamekeys', label: 'gamekeys' },
      { method: 'GET', path: 'gamekeys/keys', label: 'keys' },
      { method: 'GET', path: 'gamekeys/region-restriction', label: 'region-restriction' },
      { method: 'POST', path: 'gamekeys/order', label: 'order', paid: true }
    ]
  },
  {
    id: 'steam-topup',
    title: 'شارژ استیم',
    items: [
      { method: 'GET', path: 'steam-topup/rates', label: 'rates' },
      { method: 'POST', path: 'steam-topup/check-login', label: 'check-login' },
      { method: 'POST', path: 'steam-topup/order', label: 'order', paid: true }
    ]
  },
  {
    id: 'steam-gifts',
    title: 'گیفت استیم',
    items: [
      { method: 'GET', path: 'steam-gifts/games', label: 'games' },
      { method: 'GET', path: 'steam-gifts/games/{appid}', label: 'game' },
      { method: 'POST', path: 'steam-gifts/order', label: 'order', paid: true }
    ]
  },
  {
    id: 'telegram',
    title: 'تلگرام',
    items: [
      { method: 'GET', path: 'telegram/stars', label: 'stars' },
      { method: 'GET', path: 'telegram/premium', label: 'premium' },
      { method: 'POST', path: 'telegram/stars/buy', label: 'stars buy', paid: true },
      { method: 'POST', path: 'telegram/premium/buy', label: 'premium buy', paid: true }
    ]
  },
  {
    id: 'manual',
    title: 'سرویس دستی',
    items: [
      { method: 'GET', path: 'manual-services', label: 'list' },
      { method: 'GET', path: 'manual-services/{id}/offers', label: 'offers' },
      { method: 'POST', path: 'manual-services/order', label: 'order', paid: true },
      { method: 'GET', path: 'manual-services/orders/{orderId}/chat', label: 'chat' },
      { method: 'POST', path: 'manual-services/orders/{orderId}/chat', label: 'send chat' }
    ]
  },
  {
    id: 'funpay',
    title: 'FunPay',
    items: [
      { method: 'POST', path: 'funpay/login', label: 'login', dangerous: true },
      { method: 'GET', path: 'funpay/me', label: 'me' }
    ]
  },
  {
    id: 'webhooks',
    title: 'وب‌هوک',
    items: [
      { method: 'GET', path: 'account/webhook', label: 'get' },
      { method: 'PUT', path: 'account/webhook', label: 'put', dangerous: true },
      { method: 'DELETE', path: 'account/webhook', label: 'delete', dangerous: true },
      { method: 'POST', path: 'account/webhook/secret/regenerate', label: 'regenerate secret', dangerous: true },
      { method: 'POST', path: 'account/webhook/test', label: 'test' },
      { method: 'GET', path: 'account/webhook/deliveries', label: 'deliveries' }
    ]
  }
];
