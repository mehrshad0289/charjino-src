'use client';

import type { ReactNode } from 'react';
import { useFormik } from 'formik';
import * as Yup from 'yup';

import { Alert, Button, PasswordField, TextField, TextareaField } from '@/components/ui';
import type { FcMethod } from '@/lib/fazer-cards-endpoints';
import type { FcOpsSection } from '@/lib/fazer-cards-nav';
import type { FazerCardsStatus } from '@/types/admin';

import { FazerCardsConfirmModal } from './fazer-cards-confirm-modal';
import { FazerCardsResult } from './fazer-cards-result';
import { FazerCatalogAddPanel } from './fazer-catalog-add-panel';
import { useFazerCardsProxy, type InvokeOpts } from './use-fazer-cards-proxy';

type FazerCardsOpsScreenProps = {
  section: FcOpsSection;
  status?: FazerCardsStatus;
  error?: string;
};

export function FazerCardsOpsScreen({ section, status, error }: FazerCardsOpsScreenProps) {
  const proxy = useFazerCardsProxy();
  const form = useFormik({
    initialValues: {
      categoryId: 'pubg_mobile_auto',
      offerId: '60_uc',
      playerId: '',
      orderId: '',
      orderPage: '1',
      transactionId: '',
      paymentId: '',
      binanceOrderId: '',
      paymentMethod: 'USDT_TRC20',
      amount: '10',
      plan: 'gold',
      promo: '',
      autoRenew: 'true',
      cardId: '',
      quantity: '1',
      gameId: '',
      keyId: '',
      steamLogin: '',
      steamCurrency: 'USD',
      steamAmount: '5',
      appId: '',
      inviteUrl: '',
      subId: '',
      region: 'US',
      telegramUser: '',
      starsQty: '50',
      premiumMonths: '3',
      manualServiceId: '',
      productId: '',
      chatOrderId: '',
      chatMessage: '',
      funpayEmail: '',
      funpayPassword: '',
      webhookUrl: 'http://127.0.0.1:8000/api/webhooks/fazercards'
    },
    validationSchema: Yup.object({
      categoryId: Yup.string(),
      playerId: Yup.string(),
      amount: Yup.string(),
      funpayEmail: Yup.string().email('ایمیل نامعتبر است')
    }),
    onSubmit: async () => undefined
  });
  const v = form.values;
  const locked = proxy.busy || proxy.confirmModal.isOpen || !status?.configured;

  return (
    <div className='space-y-6'>
      {error ? <Alert variant='error' title='خطا' message={error} /> : null}
      {!status?.configured ? (
        <Alert variant='warning' title='کلید نیست' message='اول از تنظیمات کلید Reseller را ذخیره کن.' />
      ) : null}

      {['topups', 'giftcards', 'gamekeys', 'steam-gifts', 'manual'].includes(section) ? (
        <FazerCatalogAddPanel family={section} lockFamily configured={Boolean(status?.configured)} />
      ) : null}
      {section === 'steam-topup' || section === 'telegram' ? (
        <p className='text-theme-sm text-gray-500'>
          این دسته فهرست کالای جستجوپذیر ندارد؛ با همگام، نقل‌قول کامل وارد فروشگاه می‌شود.
        </p>
      ) : null}
      {section === 'funpay' ? (
        <p className='text-theme-sm text-gray-500'>FunPay در API فقط پنل دارد و محصول فروشگاه اضافه نمی‌کند.</p>
      ) : null}

      {section === 'wallet' ? (
        <Section title='حساب، والت و فاکتور'>
          <div className='grid gap-3 sm:grid-cols-2'>
            <TextField label='روش پرداخت' value={v.paymentMethod} onChange={(value) => void form.setFieldValue('paymentMethod', value)} />
            <TextField label='مبلغ USD' value={v.amount} onChange={(value) => void form.setFieldValue('amount', value)} />
            <TextField label='paymentId' value={v.paymentId} onChange={(value) => void form.setFieldValue('paymentId', value)} />
            <TextField
              label='binanceOrderId'
              value={v.binanceOrderId}
              onChange={(value) => void form.setFieldValue('binanceOrderId', value)}
            />
          </div>
          <p className='text-theme-xs text-gray-500'>
            فاکتور فقط والت USD را شارژ می‌کند؛ پلن جدا با activate-from-balance فعال می‌شود. تأیید فقط برای Binance Pay است.
          </p>
          <Row>
            <OpButton proxy={proxy} method="GET" path='me' locked={locked} variant='outline'>
              حساب
            </OpButton>
            <OpButton proxy={proxy} method="GET" path='balance' locked={locked} variant='outline'>
              موجودی
            </OpButton>
            <OpButton proxy={proxy} method="GET" path='payments/methods' locked={locked} variant='outline'>
              روش‌ها
            </OpButton>
            <OpButton proxy={proxy} method="POST" path='payments/create' locked={locked} variant='primary' extraDisabled={!v.paymentMethod || !v.amount} opts={{
                  needsConfirm: true,
                  confirmTitle: 'ساخت فاکتور والت',
                  confirmMessage: 'فاکتور شارژ والت USD ساخته می‌شود. خرید پلن خودکار نیست.',
                  body: { method: v.paymentMethod, amount: Number(v.amount) }
                }}>
              ساخت فاکتور
            </OpButton>
            <OpButton proxy={proxy} method="GET" path={`payments/${v.paymentId}`} locked={locked} variant='outline' extraDisabled={!v.paymentId}>
              جزئیات پرداخت
            </OpButton>
            <OpButton proxy={proxy} method="POST" path={`payments/${v.paymentId}/verify`} locked={locked} variant='outline' extraDisabled={!v.paymentId || !v.binanceOrderId} opts={{
                  body: { binanceOrderId: v.binanceOrderId }
                }}>
              تأیید Binance
            </OpButton>
          </Row>
        </Section>
      ) : null}

      {section === 'subscription' ? (
        <Section title='اشتراک Reseller'>
          <div className='grid gap-3 sm:grid-cols-2'>
            <TextField label='پلن (bronze/silver/gold)' value={v.plan} onChange={(value) => void form.setFieldValue('plan', value)} />
            <TextField label='کد تخفیف' value={v.promo} onChange={(value) => void form.setFieldValue('promo', value)} />
            <TextField label='auto-renew (true/false)' value={v.autoRenew} onChange={(value) => void form.setFieldValue('autoRenew', value)} />
          </div>
          <p className='text-theme-xs text-gray-500'>
            برآورد فقط وقتی اشتراک غیرفعال است. promo فقط خرید اول. ارتقای پلن فعال از والت کم می‌کند؛ کاهش پلن بدون شارژ است.
          </p>
          <Row>
            <OpButton proxy={proxy} method="GET" path='subscription' locked={locked} variant='outline'>
              وضعیت
            </OpButton>
            <OpButton proxy={proxy} method="GET" path='subscription/plans' locked={locked} variant='outline'>
              پلن‌ها
            </OpButton>
            <OpButton proxy={proxy} method="GET" path='subscription/activation-quote' locked={locked} variant='outline' extraDisabled={!v.plan} opts={{
                  query: { plan: v.plan, ...(v.promo ? { promo_code: v.promo } : {}) }
                }}>
              برآورد فعال‌سازی
            </OpButton>
            <OpButton proxy={proxy} method="POST" path='subscription/activate-from-balance' locked={locked} variant='primary' extraDisabled={!status?.liveOrderEnabled || !v.plan} opts={{
                  needsConfirm: true,
                  confirmTitle: 'فعال‌سازی پلن از والت',
                  confirmMessage: 'پلن به‌صورت خودکار خریده نمی‌شود مگر همین تأیید. از والت USD کم می‌شود.',
                  body: { plan: v.plan, promo_code: v.promo || undefined }
                }}>
              فعال‌سازی از والت
            </OpButton>
            <OpButton proxy={proxy} method="PATCH" path='subscription/auto-renew' locked={locked} variant='outline' extraDisabled={!status?.liveOrderEnabled} opts={{
                  needsConfirm: true,
                  confirmTitle: 'تغییر تمدید خودکار',
                  body: { planAutoRenew: v.autoRenew === 'true' }
                }}>
              ذخیره auto-renew
            </OpButton>
            <OpButton proxy={proxy} method="PATCH" path='subscription/plan' locked={locked} variant='outline' extraDisabled={!status?.liveOrderEnabled || !v.plan} opts={{
                  needsConfirm: true,
                  confirmTitle: 'تغییر پلن Reseller',
                  confirmMessage: 'اگر اشتراک فعال باشد، ارتقا از والت کم می‌شود. کاهش پلن بدون شارژ اعمال می‌شود.',
                  body: { plan: v.plan }
                }}>
              تغییر پلن
            </OpButton>
          </Row>
        </Section>
      ) : null}

      {section === 'orders' ? (
        <Section title='سفارش تأمین‌کننده'>
          <div className='grid gap-3 sm:grid-cols-2'>
            <TextField label='orderId عمومی (ord-…)' value={v.orderId} onChange={(value) => void form.setFieldValue('orderId', value)} />
            <TextField label='صفحه' value={v.orderPage} onChange={(value) => void form.setFieldValue('orderPage', value)} />
          </div>
          <p className='text-theme-xs text-gray-500'>لیست page از ۱ است؛ هر صفحه حداکثر ۱۰۰ ردیف.</p>
          <Row>
            <OpButton proxy={proxy} method="GET" path='orders' locked={locked} variant='outline' opts={{ query: { page: v.orderPage || '1', limit: '50' } }}>
              لیست
            </OpButton>
            <OpButton proxy={proxy} method="GET" path={`orders/${v.orderId}`} locked={locked} variant='outline' extraDisabled={!v.orderId}>
              جزئیات
            </OpButton>
          </Row>
        </Section>
      ) : null}

      {section === 'ledger' ? (
        <Section title='دفترکل'>
          <TextField
            label='transactionId'
            value={v.transactionId}
            onChange={(value) => void form.setFieldValue('transactionId', value)}
          />
          <Row>
            <OpButton proxy={proxy} method="GET" path='transactions' locked={locked} variant='outline' opts={{ query: { limit: '50' } }}>
              لیست
            </OpButton>
            <OpButton proxy={proxy} method="GET" path={`transactions/${v.transactionId}`} locked={locked} variant='outline' extraDisabled={!v.transactionId}>
              جزئیات
            </OpButton>
          </Row>
        </Section>
      ) : null}

      {section === 'topups' ? (
        <Section title='شارژ بازی'>
          <div className='grid gap-3 sm:grid-cols-2'>
            <TextField label='category_id سفارش' value={v.categoryId} onChange={(value) => void form.setFieldValue('categoryId', value)} />
            <TextField label='offer_id' value={v.offerId} onChange={(value) => void form.setFieldValue('offerId', value)} />
            <TextField label='شناسه بازیکن' value={v.playerId} onChange={(value) => void form.setFieldValue('playerId', value)} />
          </div>
          <Row>
            <OpButton proxy={proxy} method="GET" path='topups' locked={locked} variant='outline' opts={{ query: { include_ui: '1' } }}>
              دسته‌ها
            </OpButton>
            <OpButton proxy={proxy} method="GET" path='topups/offers' locked={locked} variant='outline' extraDisabled={!v.categoryId} opts={{ query: { category_id: v.categoryId, include_ui: '1' } }}>
              آفرها
            </OpButton>
            <OpButton proxy={proxy} method="GET" path='topups/validate-id' locked={locked} variant='outline'>
              کاتالوگ validate
            </OpButton>
            <OpButton proxy={proxy} method="POST" path='topups/validate-id' locked={locked} variant='outline' extraDisabled={!v.playerId} opts={{
                  body: { category_id: v.categoryId, fields: { player_id: v.playerId } }
                }}>
              اعتبارسنجی
            </OpButton>
            <OpButton proxy={proxy} method="POST" path='topups/order' locked={locked} variant='primary' extraDisabled={!status?.liveOrderEnabled} opts={{
                  needsConfirm: true,
                  confirmTitle: 'سفارش شارژ زنده',
                  confirmMessage: 'از والت USD کم می‌شود. فقط برای تست کنترل‌شده.',
                  body: {
                    category_id: v.categoryId,
                    offer_id: v.offerId,
                    fields: { player_id: v.playerId }
                  }
                }}>
              سفارش تست
            </OpButton>
          </Row>
        </Section>
      ) : null}

      {section === 'giftcards' ? (
        <Section title='گیفت کارت'>
          <div className='grid gap-3 sm:grid-cols-2'>
            <TextField label='category_id' value={v.categoryId} onChange={(value) => void form.setFieldValue('categoryId', value)} />
            <TextField label='card_id' value={v.cardId} onChange={(value) => void form.setFieldValue('cardId', value)} />
            <TextField label='تعداد' value={v.quantity} onChange={(value) => void form.setFieldValue('quantity', value)} />
          </div>
          <Row>
            <OpButton proxy={proxy} method="GET" path='giftcards' locked={locked} variant='outline'>
              دسته‌ها
            </OpButton>
            <OpButton proxy={proxy} method="GET" path='giftcards/cards' locked={locked} variant='outline' opts={{ query: v.categoryId ? { category_id: v.categoryId } : {} }}>
              کارت‌ها
            </OpButton>
            <OpButton proxy={proxy} method="POST" path='giftcards/order' locked={locked} variant='primary' extraDisabled={!status?.liveOrderEnabled} opts={{
                  needsConfirm: true,
                  body: { category_id: v.categoryId, card_id: v.cardId, quantity: Number(v.quantity) }
                }}>
              سفارش
            </OpButton>
          </Row>
        </Section>
      ) : null}

      {section === 'gamekeys' ? (
        <Section title='کلید بازی'>
          <div className='grid gap-3 sm:grid-cols-2'>
            <TextField label='game_id' value={v.gameId} onChange={(value) => void form.setFieldValue('gameId', value)} />
            <TextField label='key_id' value={v.keyId} onChange={(value) => void form.setFieldValue('keyId', value)} />
            <TextField label='تعداد' value={v.quantity} onChange={(value) => void form.setFieldValue('quantity', value)} />
          </div>
          <Row>
            <OpButton proxy={proxy} method="GET" path='gamekeys' locked={locked} variant='outline'>
              بازی‌ها
            </OpButton>
            <OpButton proxy={proxy} method="GET" path='gamekeys/keys' locked={locked} variant='outline' opts={{ query: v.gameId ? { game_id: v.gameId } : {} }}>
              کلیدها
            </OpButton>
            <OpButton proxy={proxy} method="GET" path='gamekeys/region-restriction' locked={locked} variant='outline' opts={{ query: v.keyId ? { key_id: v.keyId } : {} }}>
              محدودیت منطقه
            </OpButton>
            <OpButton proxy={proxy} method="POST" path='gamekeys/order' locked={locked} variant='primary' extraDisabled={!status?.liveOrderEnabled} opts={{
                  needsConfirm: true,
                  body: { game_id: v.gameId, key_id: v.keyId, quantity: Number(v.quantity) }
                }}>
              سفارش
            </OpButton>
          </Row>
        </Section>
      ) : null}

      {section === 'steam-topup' ? (
        <Section title='شارژ استیم'>
          <div className='grid gap-3 sm:grid-cols-2'>
            <TextField label='steamLogin' value={v.steamLogin} onChange={(value) => void form.setFieldValue('steamLogin', value)} />
            <TextField label='currency' value={v.steamCurrency} onChange={(value) => void form.setFieldValue('steamCurrency', value)} />
            <TextField label='amount' value={v.steamAmount} onChange={(value) => void form.setFieldValue('steamAmount', value)} />
          </div>
          <Row>
            <OpButton proxy={proxy} method="GET" path='steam-topup/rates' locked={locked} variant='outline'>
              نرخ‌ها
            </OpButton>
            <OpButton proxy={proxy} method="POST" path='steam-topup/check-login' locked={locked} variant='outline' extraDisabled={!v.steamLogin} opts={{ body: { steamLogin: v.steamLogin } }}>
              بررسی لاگین
            </OpButton>
            <OpButton proxy={proxy} method="POST" path='steam-topup/order' locked={locked} variant='primary' extraDisabled={!status?.liveOrderEnabled} opts={{
                  needsConfirm: true,
                  body: { steamLogin: v.steamLogin, currency: v.steamCurrency, amount: Number(v.steamAmount) }
                }}>
              سفارش
            </OpButton>
          </Row>
        </Section>
      ) : null}

      {section === 'steam-gifts' ? (
        <Section title='گیفت استیم'>
          <div className='grid gap-3 sm:grid-cols-2'>
            <TextField label='appid' value={v.appId} onChange={(value) => void form.setFieldValue('appId', value)} />
            <TextField label='invite_url' value={v.inviteUrl} onChange={(value) => void form.setFieldValue('inviteUrl', value)} />
            <TextField label='sub_id' value={v.subId} onChange={(value) => void form.setFieldValue('subId', value)} />
            <TextField label='region' value={v.region} onChange={(value) => void form.setFieldValue('region', value)} />
          </div>
          <p className='text-theme-xs text-gray-500'>
            لیست بازی هر کلید هر ۳ دقیقه یک‌بار. قیمت هر منطقه جداست. لینک دعوت فقط s.team/p.
          </p>
          <Row>
            <OpButton proxy={proxy} method="GET" path='steam-gifts/games' locked={locked} variant='outline'>
              بازی‌ها
            </OpButton>
            <OpButton proxy={proxy} method="GET" path={`steam-gifts/games/${v.appId}`} locked={locked} variant='outline' extraDisabled={!v.appId}>
              جزئیات
            </OpButton>
            <OpButton proxy={proxy} method="POST" path='steam-gifts/order' locked={locked} variant='primary' extraDisabled={!status?.liveOrderEnabled || !v.inviteUrl || !v.subId || !v.appId || !v.region} opts={{
                  needsConfirm: true,
                  confirmTitle: 'سفارش گیفت استیم زنده',
                  confirmMessage: 'از والت USD کم می‌شود. لینک باید s.team/p باشد. فقط برای تست کنترل‌شده.',
                  body: {
                    invite_url: v.inviteUrl,
                    sub_id: Number(v.subId),
                    app_id: Number(v.appId),
                    region: v.region
                  }
                }}>
              سفارش
            </OpButton>
          </Row>
        </Section>
      ) : null}

      {section === 'telegram' ? (
        <Section title='تلگرام'>
          <div className='grid gap-3 sm:grid-cols-2'>
            <TextField
              label='telegram_username'
              value={v.telegramUser}
              onChange={(value) => void form.setFieldValue('telegramUser', value)}
            />
            <TextField label='تعداد ستاره' value={v.starsQty} onChange={(value) => void form.setFieldValue('starsQty', value)} />
            <TextField label='ماه پرمیوم (۳/۶/۱۲)' value={v.premiumMonths} onChange={(value) => void form.setFieldValue('premiumMonths', value)} />
          </div>
          <Row>
            <OpButton proxy={proxy} method="GET" path='telegram/stars' locked={locked} variant='outline' opts={{ query: { quantity: v.starsQty } }}>
              قیمت ستاره
            </OpButton>
            <OpButton proxy={proxy} method="GET" path='telegram/premium' locked={locked} variant='outline' opts={{ query: { months: v.premiumMonths } }}>
              قیمت پرمیوم
            </OpButton>
            <OpButton proxy={proxy} method="POST" path='telegram/stars/buy' locked={locked} variant='primary' extraDisabled={!status?.liveOrderEnabled} opts={{
                  needsConfirm: true,
                  body: { telegram_username: v.telegramUser, quantity: Number(v.starsQty) }
                }}>
              خرید ستاره
            </OpButton>
            <OpButton proxy={proxy} method="POST" path='telegram/premium/buy' locked={locked} variant='primary' extraDisabled={!status?.liveOrderEnabled} opts={{
                  needsConfirm: true,
                  body: { telegram_username: v.telegramUser, months: Number(v.premiumMonths) }
                }}>
              خرید پرمیوم
            </OpButton>
          </Row>
        </Section>
      ) : null}

      {section === 'manual' ? (
        <Section title='سرویس دستی'>
          <div className='grid gap-3 sm:grid-cols-2'>
            <TextField
              label='manual_service_id'
              value={v.manualServiceId}
              onChange={(value) => void form.setFieldValue('manualServiceId', value)}
            />
            <TextField label='product_id' value={v.productId} onChange={(value) => void form.setFieldValue('productId', value)} />
            <TextField label='orderId چت' value={v.chatOrderId} onChange={(value) => void form.setFieldValue('chatOrderId', value)} />
            <TextareaField label='پیام چت' value={v.chatMessage} onChange={(value) => void form.setFieldValue('chatMessage', value)} rows={3} />
          </div>
          <Row>
            <OpButton proxy={proxy} method="GET" path='manual-services' locked={locked} variant='outline' opts={{ query: { include_ui: '1' } }}>
              لیست
            </OpButton>
            <OpButton proxy={proxy} method="GET" path={`manual-services/${v.manualServiceId}/offers`} locked={locked} variant='outline' extraDisabled={!v.manualServiceId} opts={{ query: { include_ui: '1' } }}>
              آفرها
            </OpButton>
            <OpButton proxy={proxy} method="POST" path='manual-services/order' locked={locked} variant='primary' extraDisabled={!status?.liveOrderEnabled || !v.manualServiceId || !v.productId} opts={{
                  needsConfirm: true,
                  confirmTitle: 'سفارش سرویس دستی زنده',
                  confirmMessage: 'از والت USD کم می‌شود. فقط برای تست کنترل‌شده.',
                  body: { manual_service_id: v.manualServiceId, product_id: v.productId, fields: {} }
                }}>
              سفارش
            </OpButton>
            <OpButton proxy={proxy} method="GET" path={`manual-services/orders/${v.chatOrderId}/chat`} locked={locked} variant='outline' extraDisabled={!v.chatOrderId}>
              خواندن چت
            </OpButton>
            <OpButton proxy={proxy} method="POST" path={`manual-services/orders/${v.chatOrderId}/chat`} locked={locked} variant='outline' extraDisabled={!v.chatOrderId} opts={{
                  body: { body: v.chatMessage }
                }}>
              ارسال چت
            </OpButton>
          </Row>
        </Section>
      ) : null}

      {section === 'funpay' ? (
        <Section title='FunPay'>
          <div className='grid gap-3 sm:grid-cols-2'>
            <TextField label='ایمیل' value={v.funpayEmail} onChange={(value) => void form.setFieldValue('funpayEmail', value)} />
            <PasswordField
              label='رمز FunPay'
              value={v.funpayPassword}
              onChange={(value) => void form.setFieldValue('funpayPassword', value)}
              autoComplete='off'
            />
          </div>
          <p className='text-theme-xs text-gray-500'>
            ورود فقط با اشتراک اصلی فعال و ادون funpay_integration. کلید برگشتی مال پنل FunPay است، روی کلید کاتالوگ ذخیره نمی‌شود و فقط چهار رقم آخر دیده می‌شود.
          </p>
          <Row>
            <OpButton proxy={proxy} method="POST" path='funpay/login' locked={locked} variant='primary' extraDisabled={!v.funpayEmail || !v.funpayPassword} opts={{
                  needsConfirm: true,
                  confirmTitle: 'ورود FunPay',
                  confirmMessage: 'ایمیل و رمز FunPay به تأمین‌کننده ارسال می‌شود. کلید کامل در پاسخ نمی‌آید و جایگزین کلید Reseller نمی‌شود.',
                  body: { email: v.funpayEmail, password: v.funpayPassword }
                }}>
              ورود
            </OpButton>
            <OpButton proxy={proxy} method="GET" path='funpay/me' locked={locked} variant='outline'>
              وضعیت پنل
            </OpButton>
          </Row>
        </Section>
      ) : null}

      {section === 'webhooks' ? (
        <Section title='وب‌هوک حساب'>
          <TextField label='URL' value={v.webhookUrl} onChange={(value) => void form.setFieldValue('webhookUrl', value)} />
          <p className='text-theme-xs text-gray-500'>گیرنده چارژینو HMAC را با FAZERCARDS_WEBHOOK_SECRET چک می‌کند.</p>
          <Row>
            <OpButton proxy={proxy} method="GET" path='account/webhook' locked={locked} variant='outline'>
              خواندن
            </OpButton>
            <OpButton proxy={proxy} method="PUT" path='account/webhook' locked={locked} variant='outline' opts={{
                  needsConfirm: true,
                  confirmTitle: 'ذخیره وب‌هوک',
                  body: { url: v.webhookUrl, events: ['order.completed', 'order.failed', 'order.refunded', 'webhook.test'] }
                }}>
              ذخیره URL
            </OpButton>
            <OpButton proxy={proxy} method="DELETE" path='account/webhook' locked={locked} variant='outline' opts={{
                  needsConfirm: true,
                  confirmTitle: 'حذف وب‌هوک',
                  confirmMessage: 'وب‌هوک حساب Reseller حذف می‌شود.'
                }}>
              حذف
            </OpButton>
            <OpButton proxy={proxy} method="POST" path='account/webhook/secret/regenerate' locked={locked} variant='primary' opts={{
                  needsConfirm: true,
                  confirmTitle: 'بازتولید secret',
                  confirmMessage: 'secret جدید فقط یک‌بار ساخته می‌شود و در بک‌اند رمز می‌گردد. مقدار کامل نشان داده نمی‌شود.'
                }}>
              بازتولید secret
            </OpButton>
            <OpButton proxy={proxy} method="POST" path='account/webhook/test' locked={locked} variant='outline'>
              تست
            </OpButton>
            <OpButton proxy={proxy} method="GET" path='account/webhook/deliveries' locked={locked} variant='outline'>
              تحویل‌ها
            </OpButton>
          </Row>
        </Section>
      ) : null}

      <FazerCardsResult last={proxy.last} />
      <FazerCardsConfirmModal
        isOpen={proxy.confirmModal.isOpen}
        title={proxy.confirmCopy.title}
        message={proxy.confirmCopy.message}
        busy={proxy.busy}
        onClose={proxy.confirmModal.closeModal}
        onConfirm={() => void proxy.confirmPending()}
      />
    </div>
  );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <div className='space-y-3 rounded-xl border border-gray-100 p-4 dark:border-white/5'>
      <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>{title}</h3>
      {children}
    </div>
  );
}

function Row({ children }: { children: ReactNode }) {
  return <div className='flex flex-wrap gap-2'>{children}</div>;
}

function OpButton({
  proxy,
  method,
  path,
  opts,
  locked,
  extraDisabled,
  variant = 'outline',
  children
}: {
  proxy: ReturnType<typeof useFazerCardsProxy>;
  method: FcMethod;
  path: string;
  opts?: InvokeOpts;
  locked: boolean;
  extraDisabled?: boolean;
  variant?: 'primary' | 'outline';
  children: ReactNode;
}) {
  return (
    <Button
      type='button'
      variant={variant}
      loading={proxy.isBusy(method, path)}
      disabled={locked || extraDisabled}
      onClick={() => void proxy.invoke(method, path, opts)}
    >
      {children}
    </Button>
  );
}
