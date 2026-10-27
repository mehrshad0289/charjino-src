'use client';

import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import Link from 'next/link';
import { useFormik } from 'formik';
import * as Yup from 'yup';
import {
  createCheckoutOrder,
  previewCheckoutCoupon,
  quoteSteamTopup,
  startOrderPayment,
  validateCheckoutPlayer,
} from '@/actions/checkout.actions';
import { UnavailableProductNotice } from '@/components/catalog/unavailable-product-notice';
import { BuyGuideTrigger } from '@/components/catalog/buy-guide/buy-guide-trigger';
import { PurchaseGuideModal } from '@/components/catalog/purchase-guide-modal';
import { hasStructuredBuyGuide } from '@/lib/buy-guide';
import { SkuPackFilters } from '@/components/catalog/sku-pack-filters';
import { Button, CloseIcon, LoadingOverlay, SelectField } from '@/components/ui';
import { hotjarSuppress } from '@/lib/hotjar';
import {
  purchaseSchemaOf,
  quantityEnabled,
  resolveInputFields,
  schemaType,
  selectOptions,
  sellPriceToman,
  toRequirements,
  validationMode,
} from '@/lib/buyer-input-fields';
import { gameKeyCardPlatformLabel, gameKeyPlatformLabel, isGameKeyProduct } from '@/lib/catalog-family';
import { isOrderable } from '@/lib/catalog-orderable';
import { catalogRegionLabel } from '@/lib/catalog-region';
import {
  codmMatchCaption,
  codmMismatchCaption,
  codmSuggestedHref,
  isCodmRegionCheck,
  type CodmRegionPayload,
} from '@/lib/codm-region';
import { onlyDigits } from '@/lib/fa';
import { preferredSkuId, resolveSelectedSkuId } from '@/lib/catalog-sku-param';
import { initialFacets, packFiltersOf, visibleSkus } from '@/lib/sku-pack-filters';
import { customerErrorMessage } from '@/lib/customer-errors';
import { checkoutIdempotencyKey } from '@/lib/idempotency';
import { continueShopPayment } from '@/lib/payment-return';
import { formatFaDate, toFaDigits } from '@/lib/fa';
import { formatTomanNumber, tomanPrice } from '@/lib/price';
import { notify } from '@/lib/toast';
import { authHref } from '@/lib/safe-path';
import { ROUTES } from '@/lib/routes';
import { fieldHint, fieldPreviewState } from '@/lib/validation';
import type { CatalogInputField, CatalogProduct, CatalogSku } from '@/types/catalog';

const idFieldClass =
  'h-11 w-full rounded-xl border border-border bg-card px-3 text-sm text-text shadow-card outline-none placeholder:text-text/45 focus:border-primary-600 focus:ring-2 focus:ring-primary-600/20';

type ProductBuyFormProps = {
  product: CatalogProduct;
  skus: CatalogSku[];
  signedIn?: boolean;
  returnTo: string;
  info?: ReactNode;
  initialSkuFilters?: Record<string, string | undefined>;
  initialSkuId?: number | null;
  hasStructuredGuide?: boolean;
};

function yupForField(field: CatalogInputField, digitsOnly: boolean) {
  let schema = Yup.string().trim();
  if (field.required) {
    schema = schema.required(`${field.label} الزامی است`);
  }
  if (digitsOnly) {
    const source = field.pattern && field.pattern.includes('{') ? field.pattern : '^\\d+$';
    try {
      return schema.matches(
        new RegExp(source),
        `${field.label} فقط رقم باشد (۵ تا ۲۵ رقم، زیر نیک‌نیم در Player Profile).`,
      );
    } catch {
      return schema.matches(/^\d+$/, `${field.label} فقط رقم باشد.`);
    }
  }
  if (field.pattern) {
    try {
      schema = schema.matches(new RegExp(field.pattern), 'قالب این فیلد درست نیست.');
    } catch {
      /* pattern from catalog may be invalid in JS */
    }
  }
  return schema;
}

function maskIdPreview(value: string) {
  const digits = value.replace(/\s+/g, '');
  if (digits.length <= 4) return digits;
  return `${digits.slice(0, 4)}…`;
}

function regionLabel(code: unknown) {
  const text = typeof code === 'string' ? code : '';
  return catalogRegionLabel(text) || text;
}

export function ProductBuyForm({
  product,
  skus,
  signedIn = false,
  returnTo,
  info,
  initialSkuFilters,
  initialSkuId = null,
  hasStructuredGuide,
}: ProductBuyFormProps) {
  const structuredGuide = hasStructuredGuide ?? hasStructuredBuyGuide(product.buyGuide);
  const schema = purchaseSchemaOf(product);
  const family = schemaType(product);
  const fields = useMemo(() => resolveInputFields(product), [product]);
  const packFilters = useMemo(() => packFiltersOf(product), [product]);
  const [facets, setFacets] = useState(() => initialFacets(packFilters, initialSkuFilters));
  const [skuQuery, setSkuQuery] = useState('');
  const listedSkus = useMemo(() => visibleSkus(skus, facets, skuQuery), [skus, facets, skuQuery]);
  const [skuId, setSkuId] = useState<number | null>(() => resolveSelectedSkuId(skus, listedSkus, initialSkuId));
  const [quantity, setQuantity] = useState(() => {
    const selectedId = resolveSelectedSkuId(skus, listedSkus, initialSkuId);
    const selectedSku = skus.find((sku) => sku.id === selectedId) ?? listedSkus[0] ?? skus[0];
    return Math.max(1, selectedSku?.minOrderQuantity ?? schema?.quantity?.min ?? 1);
  });
  const [formMessage, setFormMessage] = useState<string | null>(null);
  const [checking, setChecking] = useState(false);
  const [paying, setPaying] = useState(false);
  const [checkState, setCheckState] = useState<'idle' | 'ok' | 'soft' | 'blocked'>('idle');
  const [checkCaption, setCheckCaption] = useState<string | null>(null);
  const [ackId, setAckId] = useState(false);
  const [fallbackAck, setFallbackAck] = useState(false);
  const [regionAck, setRegionAck] = useState(false);
  const [codmSuggestHref, setCodmSuggestHref] = useState<string | null>(null);
  const [unverifiedNotice, setUnverifiedNotice] = useState(false);
  const [guideOpen, setGuideOpen] = useState(false);
  const [steamToman, setSteamToman] = useState<number | null>(null);
  const [steamQuoteError, setSteamQuoteError] = useState<string | null>(null);
  const [couponInput, setCouponInput] = useState('');
  const [couponBusy, setCouponBusy] = useState(false);
  const [couponError, setCouponError] = useState<string | null>(null);
  const [appliedCoupon, setAppliedCoupon] = useState<{
    code: string;
    subtotalToman: number;
    discountToman: number;
    payableToman: number;
  } | null>(null);
  const payKeyRef = useRef<string | null>(null);

  const formik = useFormik<Record<string, string>>({
    enableReinitialize: true,
    initialValues: {
      ...Object.fromEntries(fields.map((field) => [field.name, ''])),
      ...(family === 'steam-topup' ? { currency: 'USD', amount: '' } : {}),
    },
    validationSchema: Yup.object({
      ...Object.fromEntries(fields.map((field) => [field.name, yupForField(field, Boolean(product.codm))])),
      ...(family === 'steam-topup'
        ? {
            currency: Yup.string().required('ارز را انتخاب کنید'),
            amount: Yup.string().trim().required('مبلغ را وارد کنید'),
          }
        : {}),
    }),
    onSubmit: async () => {
      await handlePay();
    },
  });

  useEffect(() => {
    payKeyRef.current = null;
    setAppliedCoupon(null);
    setCouponError(null);
  }, [skuId, quantity, steamToman]);

  useEffect(() => {
    if (skuId && listedSkus.some((sku) => sku.id === skuId)) {
      return;
    }
    if (skuId && preferredSkuId(skus, initialSkuId) === skuId) {
      return;
    }
    const next = listedSkus[0];
    setSkuId(next?.id ?? null);
    if (next) {
      setQuantity(Math.max(1, next.minOrderQuantity ?? 1));
    }
  }, [listedSkus, skuId, initialSkuId, skus]);

  const selected = skus.find((sku) => sku.id === skuId) ?? listedSkus[0] ?? null;
  const selectedPrice = selected ? sellPriceToman(selected) : 0;
  const minQty = Math.max(1, selected?.minOrderQuantity ?? schema?.quantity?.min ?? 1);
  const stockCap = selected?.stock != null ? Math.max(0, selected.stock) : 100;
  const maxQty = Math.max(minQty, Math.min(100, selected?.maxOrderQuantity ?? schema?.quantity?.max ?? 100, stockCap || 100));
  const showQuantity = quantityEnabled(product);
  const requirements = toRequirements(schema, formik.values);
  const payable =
    family === 'steam-topup' ? (steamToman ?? 0) : selectedPrice * (showQuantity ? quantity : 1);
  const mode = validationMode(product);
  const regionCheck = isCodmRegionCheck(product);
  const ackMode = regionCheck
    ? fallbackAck || checkState === 'soft'
    : mode === 'ack' || fallbackAck || Boolean(product.codm);
  const checkMode = regionCheck || (!ackMode && (mode === 'fazer' || mode === 'steam_check'));
  const orderable = isOrderable(product);
  const identityReady = ackMode
    ? ackId
    : checkMode
      ? checkState === 'ok'
      : family === 'steam-gift'
        ? regionAck
        : true;
  const fieldsReady = fields.every((field) => !field.required || String(formik.values[field.name] ?? '').trim() !== '');
  const steamReady =
    family !== 'steam-topup' ||
    (Boolean((formik.values.currency || '').trim()) && Boolean((formik.values.amount || '').trim()) && (steamToman ?? 0) > 0);
  const canSubmit = Boolean(
    orderable &&
      selected &&
      payable > 0 &&
      !checking &&
      !paying &&
      identityReady &&
      checkState !== 'blocked' &&
      fieldsReady &&
      steamReady,
  );
  const selectedVersion = product.codm?.versions.find((row) => row.selected);
  const idPreview = fields.map((field) => String(formik.values[field.name] ?? '').replace(/\s+/g, '')).find(Boolean) ?? '';
  const ackSummary = [selectedVersion?.label, idPreview ? `شناسه: ${maskIdPreview(idPreview)}` : null, selected?.name]
    .filter(Boolean)
    .join(' — ');
  const loginHref = authHref(returnTo);
  const regionNote = product.regionNote || schema?.regionNote || null;
  const availability = product.regionAvailability;
  const currencies = schema?.amount?.currencies ?? ['USD', 'RUB', 'UAH', 'KZT'];

  useEffect(() => {
    if (family !== 'steam-topup') return;
    const currency = formik.values.currency || 'USD';
    const amount = (formik.values.amount || '').trim();
    if (!amount) {
      setSteamToman(null);
      return;
    }
    const timer = window.setTimeout(() => {
      void quoteSteamTopup(currency, amount).then((result) => {
        if (!result.ok) {
          setSteamToman(null);
          setSteamQuoteError(customerErrorMessage(result.errorCode, result.message));
          return;
        }
        setSteamQuoteError(null);
        setSteamToman(result.data.toman);
      });
    }, 400);
    return () => window.clearTimeout(timer);
  }, [family, formik.values.amount, formik.values.currency]);

  async function ensureValid() {
    const errors = await formik.validateForm();
    if (Object.keys(errors).length > 0) {
      await formik.setTouched(Object.fromEntries(Object.keys(formik.values).map((key) => [key, true])), true);
      const first = Object.values(errors).find((value) => typeof value === 'string');
      if (first) notify.error(first);
      return false;
    }
    if (!selected) {
      notify.error('بسته را انتخاب کن.');
      return false;
    }
    if (payable <= 0) {
      notify.error('قیمت این بسته در دسترس نیست.');
      return false;
    }
    return true;
  }

  function identityCacheKey(sku: number, req: Record<string, string>) {
    return `charjino:idcheck:${sku}:${JSON.stringify(req)}`;
  }

  async function handleCheckPlayer(fromBlur = false) {
    if (!selected || !checkMode) return;
    setCodmSuggestHref(null);
    const missing = fields.some((field) => field.required && !String(formik.values[field.name] ?? '').trim());
    if (missing) return;
    if (family === 'steam-topup' && !(formik.values.steamLogin || '').trim()) return;
    if (!fromBlur && !(await ensureValid())) return;
    const key = identityCacheKey(selected.id, requirements);
    try {
      const cached = sessionStorage.getItem(key);
      if (cached) {
        const parsed = JSON.parse(cached) as { ok: true; playerName?: string; unverified?: boolean } | { ok: false; code: string; message: string };
        if (parsed.ok) {
          applyCheckOk(parsed.playerName, Boolean(parsed.unverified));
          return;
        }
        applyCheckFail(parsed.code, parsed.message);
        return;
      }
    } catch {
      /* ignore */
    }
    setChecking(true);
    setFormMessage(null);
    const result = await validateCheckoutPlayer(selected.id, requirements);
    setChecking(false);
    if (!result.ok) {
      const code = result.errorCode ?? '';
      if (code === 'CODM_REGION_MISMATCH') {
        const payload = (result.data ?? null) as CodmRegionPayload | null;
        const text = result.message || codmMismatchCaption(payload);
        setCodmSuggestHref(codmSuggestedHref(payload));
        applyCheckFail(code, text);
        if (!fromBlur) notify.error(text);
        return;
      }
      if (code === 'PLAYER_ID_INVALID') {
        applyCheckFail(code, result.message);
        if (!fromBlur) notify.error(result.message);
        return;
      }
      if (
        code === 'STEAM_LOGIN_CANNOT_REFILL' ||
        code === 'PLAYER_NOT_FOUND' ||
        code === 'REGION_MISMATCH' ||
        code === 'ID_NOT_CONFIRMED'
      ) {
        applyCheckFail(code, result.message);
        try {
          sessionStorage.setItem(key, JSON.stringify({ ok: false, code, message: result.message }));
        } catch {
          /* ignore */
        }
        if (!fromBlur) notify.error(result.message);
        return;
      }
      if (
        family === 'steam-topup' ||
        code === 'SUPPLIER_UNREACHABLE' ||
        code === 'SUPPLIER_TIMEOUT' ||
        code === 'SUPPLIER_5XX' ||
        code === 'SUPPLIER_UNAVAILABLE'
      ) {
        if (family === 'steam-topup') {
          applyCheckOk(null, true);
          return;
        }
        setFallbackAck(true);
        setCheckState('soft');
        setCheckCaption('بررسی شناسه موقتاً در دسترس نیست. نسخه و شناسه را تأیید کن.');
        return;
      }
      applyCheckFail(code, result.message);
      return;
    }
    const unverified = Boolean(result.data.unverified) || result.data.validation === 'unverified';
    const region = result.data.codmRegion;
    if (regionCheck && region?.status === 'unknown') {
      setFallbackAck(true);
      setCheckState('soft');
      setUnverifiedNotice(false);
      setCheckCaption(
        `بررسی خودکار ریجن اکانت الان ممکن نشد. اگر مطمئن هستید اکانتتان در ریجن ${codmCountryLabel()} ثبت شده، تیک زیر را بزنید. شارژ به اکانت ریجن دیگر انجام نمی‌شود.`,
      );
      return;
    }
    applyCheckOk(result.data.playerName, unverified, region ?? undefined);
    try {
      sessionStorage.setItem(
        key,
        JSON.stringify({ ok: true, playerName: result.data.playerName, unverified, codmRegion: region }),
      );
    } catch {
      /* ignore */
    }
  }

  function codmCountryLabel() {
    return (
      product.codm?.versions.find((row) => row.selected)?.label?.replace(/^Activision\s+/u, '') ||
      'همین محصول'
    );
  }

  function applyCheckOk(
    playerName: string | null | undefined,
    unverified: boolean,
    region?: CodmRegionPayload,
  ) {
    setFallbackAck(false);
    setCheckState('ok');
    setAckId(false);
    setUnverifiedNotice(unverified);
    setCheckCaption(
      family === 'steam-topup'
        ? unverified
          ? 'بررسی لاگین انجام نشد؛ می‌توانید ادامه دهید.'
          : 'لاگین قابل شارژ است'
        : regionCheck && region?.status === 'match'
          ? `✓ ${codmMatchCaption(region)}`
          : playerName?.trim()
            ? `اسم بازیکن: ${playerName}`
            : 'شناسه تأیید شد',
    );
  }

  function applyCheckFail(code: string, message: string) {
    setCheckState('blocked');
    setUnverifiedNotice(false);
    const text =
      code === 'ID_NOT_CONFIRMED'
        ? 'شناسه تأیید نشد؛ دوباره بررسی کن یا با پشتیبانی تماس بگیر'
        : code === 'CODM_REGION_MISMATCH'
          ? message
          : customerErrorMessage(code, message);
    setCheckCaption(text);
    setFormMessage(text);
  }

  function clearCoupon() {
    setAppliedCoupon(null);
    setCouponError(null);
    payKeyRef.current = null;
  }

  async function applyCoupon() {
    if (!selected) {
      notify.error('بسته را انتخاب کن.');
      return;
    }
    if (!couponInput.trim()) {
      setCouponError('کد تخفیف را بنویس');
      return;
    }
    setCouponBusy(true);
    setCouponError(null);
    const extra =
      family === 'steam-topup'
        ? { amount: formik.values.amount, currency: formik.values.currency }
        : undefined;
    const result = await previewCheckoutCoupon(
      couponInput,
      selected.id,
      showQuantity ? quantity : 1,
      extra,
    );
    setCouponBusy(false);
    if (!result.ok) {
      setAppliedCoupon(null);
      setCouponError(result.message);
      notify.error(result.message);
      return;
    }
    setAppliedCoupon({
      code: result.data.code,
      subtotalToman: result.data.subtotalToman,
      discountToman: result.data.discountToman,
      payableToman: result.data.payableToman,
    });
    payKeyRef.current = null;
    notify.success('کد تخفیف اعمال شد.');
  }

  async function handlePay() {
    if (!orderable) return;
    if (!(await ensureValid()) || !selected) return;
    if (!identityReady) {
      notify.error(
        ackMode
          ? 'نسخه و شناسه را تأیید کن.'
          : family === 'steam-gift'
            ? 'ریجن حساب استیم باید با همین محصول یکی باشد.'
            : 'اول شناسه را بررسی کن.',
      );
      return;
    }
    setPaying(true);
    setFormMessage(null);
    const extra = {
      ...(family === 'steam-topup' ? { amount: formik.values.amount, currency: formik.values.currency } : {}),
      ...(appliedCoupon?.code ? { couponCode: appliedCoupon.code } : {}),
    };
    const key = payKeyRef.current ?? checkoutIdempotencyKey();
    payKeyRef.current = key;
    const payload = {
      ...requirements,
      ...(ackMode ? { id_ack: '1' } : {}),
      ...(family === 'steam-gift' ? { region_ack: '1' } : {}),
    };
    const created = await createCheckoutOrder(
      selected.id,
      payload,
      key,
      showQuantity ? quantity : 1,
      extra,
    );
    if (!created.ok) {
      payKeyRef.current = null;
      setPaying(false);
      setFormMessage(created.message);
      notify.error(created.message);
      return;
    }

    const pay = await startOrderPayment(created.data.number, window.location.origin);
    if (!pay.ok) {
      setPaying(false);
      const text = `${pay.message} شماره سفارش: ${created.data.number}. از پیگیری سفارش ادامه بده.`;
      setFormMessage(text);
      notify.error(text);
      return;
    }

    const next = await continueShopPayment(pay.data);
    if (!next.ok) {
      setPaying(false);
      setFormMessage(next.message);
      notify.error(next.message);
      return;
    }

    window.location.assign(next.href);
  }

  return (
    <form className="flex flex-col gap-8 lg:contents" onSubmit={formik.handleSubmit}>
      <LoadingOverlay open={checking || paying} label={paying ? 'در حال رفتن به درگاه...' : 'در حال بررسی شناسه...'} />
      <div className="flex min-w-0 flex-col gap-4 lg:col-span-6 lg:py-8">
        {info}
        {regionNote ? (
          <p role="note" className="rounded-2xl bg-card px-4 py-3 text-sm leading-7 text-text/80 ring-1 ring-border">
            {regionNote}
          </p>
        ) : null}
        {family === 'gamekey' && availability ? (
          <div className="rounded-2xl bg-card px-4 py-3 text-sm leading-7 text-text/80 ring-1 ring-border">
            {Array.isArray(availability.available) && availability.available.length > 0 ? (
              <p>قابل فعال‌سازی در: {availability.available.map(regionLabel).join('، ')}</p>
            ) : null}
            {Array.isArray(availability.unavailable) && availability.unavailable.length > 0 ? (
              <p>غیرقابل فعال‌سازی در: {availability.unavailable.map(regionLabel).join('، ')}</p>
            ) : null}
          </div>
        ) : null}
        {family === 'steam-gift' ? (
          <p role="note" className="rounded-2xl bg-card px-4 py-3 text-sm leading-7 text-text/80 ring-1 ring-border">
            ریجن حساب استیم گیرنده باید با ریجن انتخابی یکی باشد.
          </p>
        ) : null}
        {family === 'giftcard' ? (
          <p className="text-sm leading-7 text-text/70">
            کد پس از پرداخت در صفحهٔ سفارش نمایش داده می‌شود و می‌توانید آن را نگه دارید و بعداً ردیم کنید.
          </p>
        ) : null}
        {product.codm && product.codm.versions.length > 0 ? (
          <fieldset className="flex min-w-0 flex-col gap-2">
            <legend className="mb-1 text-sm text-text/70">انتخاب نسخه</legend>
            <p role="note" className="text-sm leading-7 text-text/70">
              {product.codm.hint}{' '}
              <Link href={product.codm.guideHref || ROUTES.blogPost('codm-region')} className="text-primary-600">
                ریجن کالاف چیست؟
              </Link>
            </p>
            <div className="flex flex-col gap-2">
              {product.codm.versions.map((version) => (
                <Link
                  key={version.handle}
                  href={ROUTES.catalog(version.handle)}
                  className={`flex w-full min-w-0 items-center gap-3 rounded-2xl px-3 py-3 ring-1 sm:px-4 ${
                    version.selected ? 'bg-card-active ring-primary-600' : 'bg-card ring-transparent hover:ring-border'
                  }`}
                >
                  <span
                    className={`size-4 shrink-0 rounded-full ring-1 ${
                      version.selected ? 'bg-primary-600 ring-primary-600' : 'bg-transparent ring-border'
                    }`}
                    aria-hidden
                  />
                  <span className="text-sm text-text">{version.label}</span>
                </Link>
              ))}
            </div>
          </fieldset>
        ) : null}
        {skus.length > 0 ? (
          <fieldset disabled={!orderable} className={`flex min-w-0 flex-col gap-2 ${orderable ? '' : 'pointer-events-none opacity-50'}`}>
            <legend className="mb-1 text-sm text-text/70">
              {orderable
                ? family === 'steam-gift'
                  ? 'انتخاب نسخه و ریجن'
                  : family === 'telegram-premium'
                    ? 'مدت پرمیوم'
                    : 'انتخاب بسته'
                : 'بسته‌ها'}
              {listedSkus.length !== skus.length ? (
                <span className="ms-1 text-text/45">
                  ({toFaDigits(listedSkus.length)} از {toFaDigits(skus.length)})
                </span>
              ) : null}
            </legend>
            <SkuPackFilters
              filters={packFilters}
              value={facets}
              onChange={(key, next) => {
                setFacets((current) => ({ ...current, [key]: next }));
                setFormMessage(null);
                setCheckState('idle');
              }}
            />
            {skus.length > 8 ? (
              <label className="flex flex-col gap-2">
                <span className="sr-only">جستجوی بسته</span>
                <input
                  {...hotjarSuppress}
                  className={idFieldClass}
                  value={skuQuery}
                  placeholder="جستجوی بسته…"
                  onChange={(event) => setSkuQuery(event.target.value)}
                />
              </label>
            ) : null}
            <div className="flex flex-col gap-2">
              {listedSkus.length === 0 ? (
                <p className="rounded-2xl bg-card px-4 py-3 text-sm text-text/60 ring-1 ring-border">
                  با این فیلتر بسته‌ای پیدا نشد.
                </p>
              ) : null}
              {listedSkus.map((sku) => {
                const price = sellPriceToman(sku);
                const original = tomanPrice(sku.originalPrice);
                const regionText = sku.region ? regionLabel(sku.region) : '';
                return (
                  <label
                    key={sku.id}
                    className={`flex w-full min-w-0 items-center justify-between gap-3 overflow-hidden rounded-2xl px-3 py-3 ring-1 sm:px-4 ${
                      orderable ? 'cursor-pointer' : 'cursor-default'
                    } ${
                      skuId === sku.id ? 'bg-card-active ring-primary-600' : 'bg-card ring-transparent'
                    }`}
                  >
                    <span className="flex min-w-0 flex-1 items-center gap-2 text-sm text-text sm:gap-3">
                      <input
                        {...hotjarSuppress}
                        type="radio"
                        name="sku"
                        className="shrink-0"
                        disabled={!orderable}
                        checked={skuId === sku.id}
                        onChange={() => {
                          setSkuId(sku.id);
                          setQuantity(Math.max(1, sku.minOrderQuantity ?? 1));
                          setFormMessage(null);
                          setCheckState('idle');
                        }}
                      />
                      <span className="min-w-0 flex-1 leading-6">
                        <span className="line-clamp-2 break-words">{sku.name}</span>
                        {regionText ? (
                          <span className="mt-0.5 block text-xs text-text/55">{regionText}</span>
                        ) : null}
                        {original > price && sku.saleEndsAt ? (
                          <span className="mt-0.5 block text-xs font-medium text-secondary-600">
                            تخفیف تا {formatFaDate(sku.saleEndsAt)}
                          </span>
                        ) : null}
                      </span>
                    </span>
                    <span className="max-w-[42%] shrink-0 text-end text-sm font-semibold break-words text-primary-600">
                      {family === 'steam-topup' ? (
                        'مبلغ آزاد'
                      ) : price > 0 ? (
                        <>
                          {original > price ? (
                            <span className="block text-xs font-normal text-text-muted line-through">
                              {formatTomanNumber(original)} تومان
                            </span>
                          ) : null}
                          {formatTomanNumber(price)} تومان
                        </>
                      ) : (
                        'قیمت ناموجود'
                      )}
                    </span>
                  </label>
                );
              })}
            </div>
          </fieldset>
        ) : (
          <p className="text-sm text-text/50">بسته فعالی برای این محصول نیست.</p>
        )}
        {orderable && showQuantity && selected ? (
          <div className="flex flex-col gap-2">
            <span className="text-sm text-text/70">{family === 'giftcard' ? 'تعداد کد' : 'تعداد کلید'}</span>
            <div className="flex items-center gap-3">
              <button
                type="button"
                aria-label="کم کردن تعداد"
                disabled={quantity <= minQty}
                onClick={() => setQuantity((value) => Math.max(minQty, value - 1))}
                className="inline-flex size-11 items-center justify-center rounded-xl border border-border bg-card text-lg text-text shadow-card disabled:opacity-40"
              >
                −
              </button>
              <span className="min-w-10 text-center text-base font-semibold tabular-nums text-text">
                {toFaDigits(quantity)}
              </span>
              <button
                type="button"
                aria-label="افزودن تعداد"
                disabled={quantity >= maxQty}
                onClick={() => setQuantity((value) => Math.min(maxQty, value + 1))}
                className="inline-flex size-11 items-center justify-center rounded-xl border border-border bg-card text-lg text-text shadow-card disabled:opacity-40"
              >
                +
              </button>
            </div>
          </div>
        ) : null}
      </div>

      <div className="flex flex-col gap-4 lg:sticky lg:top-(--shop-chrome-top) lg:z-10 lg:col-span-3 lg:max-h-[calc(100svh-var(--shop-chrome-top))] lg:self-start lg:overflow-y-auto lg:py-8">
        {!orderable ? <UnavailableProductNotice product={product} /> : null}
        {orderable
          ? fields.map((field) => (
          <SchemaField
            key={field.name}
            field={field}
            family={family}
            formik={formik}
            digitsOnly={Boolean(product.codm)}
            stripSpaces={family === 'topup' || family === 'steam-topup' || family === 'telegram-stars' || family === 'telegram-premium'}
            onChangeExtra={() => {
              setFormMessage(null);
              setCheckState('idle');
              setAckId(false);
              setFallbackAck(false);
              setUnverifiedNotice(false);
              setCheckCaption(null);
              setCodmSuggestHref(null);
            }}
            onBlurExtra={() => {
              if (checkMode) void handleCheckPlayer(true);
            }}
          />
        ))
          : null}

        {orderable && family === 'steam-topup' ? (
          <>
            <SelectField
              label="ارز"
              variant="shop"
              placeholder="ارز"
              options={currencies.map((value) => ({ value, label: value }))}
              value={formik.values.currency ?? 'USD'}
              onChange={(value) => {
                void formik.setFieldValue('currency', value);
                setCheckState('idle');
              }}
            />
            <label className="flex flex-col gap-2">
              <span className="text-sm text-text">مبلغ</span>
              <input
                {...hotjarSuppress}
                className={idFieldClass}
                dir="ltr"
                inputMode="decimal"
                placeholder={formik.values.currency === 'USD' ? 'مثلاً 10.00' : 'مثلاً 500.5'}
                value={formik.values.amount ?? ''}
                onChange={(event) => {
                  void formik.setFieldValue('amount', event.target.value);
                }}
              />
              <span className="text-xs text-text/55">
                USD حداکثر ۲ رقم اعشار؛ بقیه حداکثر ۴ رقم. حدود از تنظیمات شارژینو است.
              </span>
              {steamQuoteError ? <span className="text-xs text-error-200">{steamQuoteError}</span> : null}
              {steamToman ? (
                <span className="text-sm font-semibold text-primary-600">{formatTomanNumber(steamToman)} تومان</span>
              ) : null}
            </label>
          </>
        ) : null}

        {orderable && checkCaption ? (
          <div
            className={`rounded-2xl px-4 py-3 text-sm leading-7 ring-1 ${
              checkState === 'blocked'
                ? 'bg-card text-error-200 ring-error-200/40'
                : checkState === 'ok'
                  ? 'bg-card text-primary-600 ring-primary-600/40'
                  : 'bg-card text-text/80 ring-border'
            }`}
          >
            <p>{checkCaption}</p>
            {checkState === 'blocked' && codmSuggestHref ? (
              <Link href={codmSuggestHref} className="mt-2 inline-flex text-sm font-semibold text-primary-600">
                رفتن به محصول مناسب
              </Link>
            ) : null}
            {checkState === 'blocked' && regionCheck && !codmSuggestHref ? (
              <Link
                href={product.codm?.guideHref || ROUTES.blogPost('codm-region')}
                className="mt-2 inline-flex text-sm font-semibold text-primary-600"
              >
                ریجن کالاف چیست؟
              </Link>
            ) : null}
          </div>
        ) : null}
        {orderable && regionCheck && checkState === 'idle' && signedIn ? (
          <Button type="button" variant="outline" fullWidth loading={checking} onClick={() => void handleCheckPlayer()}>
            بررسی ریجن اکانت
          </Button>
        ) : null}
        {orderable && unverifiedNotice && family === 'steam-topup' ? (
          <p role="note" className="text-sm leading-7 text-text/80">
            بررسی لاگین انجام نشد؛ می‌توانید ادامه دهید.
          </p>
        ) : null}
        {orderable && ackMode ? (
          <div className="flex flex-col gap-3 rounded-2xl bg-card p-4 ring-1 ring-border">
            {ackSummary ? <p className="text-sm leading-7 text-text">{ackSummary}</p> : null}
            <label className="flex items-start gap-2 text-sm text-text">
              <input {...hotjarSuppress} type="checkbox" checked={ackId} onChange={(event) => setAckId(event.target.checked)} />
              {regionCheck
                ? `مطمئنم اکانتم ریجن ${codmCountryLabel()} است`
                : 'نسخه و شناسه درست است'}
            </label>
          </div>
        ) : null}
        {orderable && family === 'steam-gift' ? (
          <label className="flex items-start gap-2 text-sm text-text">
            <input {...hotjarSuppress} type="checkbox" checked={regionAck} onChange={(event) => setRegionAck(event.target.checked)} />
            ریجن حساب استیم گیرنده با همین محصول یکی است
          </label>
        ) : null}

        {formMessage ? <p className="text-sm text-error-200">{formMessage}</p> : null}

        {orderable && signedIn ? (
          <div className="flex flex-col gap-2">
            <span className="text-sm font-bold text-text">کد تخفیف</span>
            {appliedCoupon ? (
              <div className="flex items-center justify-between gap-3 rounded-xl border border-border bg-card px-3 py-2.5 shadow-card">
                <p className="min-w-0 text-sm text-text">
                  <span className="font-semibold">{appliedCoupon.code}</span>
                  <span className="text-text-muted">
                    {' '}
                    · {formatTomanNumber(appliedCoupon.discountToman)} تومان تخفیف
                  </span>
                </p>
                <button type="button" className="shrink-0 text-xs text-primary-600" onClick={clearCoupon}>
                  حذف
                </button>
              </div>
            ) : (
              <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input
                  {...hotjarSuppress}
                  className={`${idFieldClass} ${couponError ? 'border-error-500 focus:border-error-500 focus:ring-error-500/20' : ''}`}
                  dir="ltr"
                  autoComplete="off"
                  spellCheck={false}
                  placeholder="کد تخفیف را وارد کنید"
                  value={couponInput}
                  onChange={(event) => {
                    setCouponInput(event.target.value.toUpperCase());
                    setCouponError(null);
                  }}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                      event.preventDefault();
                      void applyCoupon();
                    }
                  }}
                />
                <Button
                  type="button"
                  variant="outline"
                  size="L"
                  loading={couponBusy}
                  className="w-full shrink-0 sm:w-auto"
                  onClick={() => void applyCoupon()}
                >
                  اعمال کد
                </Button>
              </div>
            )}
            {couponError ? <p className="text-xs text-error-500">{couponError}</p> : null}
          </div>
        ) : null}

        {orderable && (showQuantity || family === 'steam-topup' || appliedCoupon) ? (
          <div className="rounded-2xl bg-card p-4 ring-1 ring-border">
            <h2 className="text-sm font-semibold text-text">فاکتور خرید</h2>
            <dl className="mt-3 space-y-2 text-sm">
              <InvoiceRow label="محصول" value={product.title} />
              <InvoiceRow label="بسته" value={selected?.name || 'انتخاب نشده'} />
              {product.platform ? (
                <InvoiceRow
                  label="پلتفرم"
                  value={
                    isGameKeyProduct(product)
                      ? gameKeyCardPlatformLabel(product.platform) || product.platform
                      : gameKeyPlatformLabel(product.platform) || product.platform
                  }
                />
              ) : null}
              <InvoiceRow
                label="ریجن"
                value={
                  selected?.region
                    ? regionLabel(selected.region)
                    : product.region
                      ? regionLabel(product.region)
                      : '—'
                }
              />
              {showQuantity ? <InvoiceRow label="تعداد" value={toFaDigits(quantity)} /> : null}
              <InvoiceRow
                label="مبلغ"
                value={payable > 0 ? `${formatTomanNumber(payable)} تومان` : 'قیمت ناموجود'}
              />
              {appliedCoupon ? (
                <>
                  <InvoiceRow label="تخفیف" value={`${formatTomanNumber(appliedCoupon.discountToman)} تومان`} />
                  <InvoiceRow
                    label="قابل پرداخت"
                    value={`${formatTomanNumber(appliedCoupon.payableToman)} تومان`}
                    emphasis
                  />
                </>
              ) : (
                <InvoiceRow
                  label="قابل پرداخت"
                  value={payable > 0 ? `${formatTomanNumber(payable)} تومان` : 'قیمت ناموجود'}
                  emphasis
                />
              )}
            </dl>
          </div>
        ) : null}

        {structuredGuide ? (
          <BuyGuideTrigger title={product.category?.title ? `راهنمای خرید ${product.category.title}` : 'راهنمای خرید'} />
        ) : (
          <Button type="button" variant="outline" fullWidth onClick={() => setGuideOpen(true)}>
            راهنمای خرید
          </Button>
        )}

        {!orderable ? null : signedIn ? (
          <Button type="submit" size="L" fullWidth loading={paying} disabled={!canSubmit}>
            خرید
          </Button>
        ) : (
          <Button href={loginHref} size="L" fullWidth>
            ورود با پیامک و ادامه خرید
          </Button>
        )}
      </div>
      {structuredGuide ? null : (
        <PurchaseGuideModal
          open={guideOpen}
          onClose={() => setGuideOpen(false)}
          title={product.category?.title ? `راهنمای خرید ${product.category.title}` : 'راهنمای خرید'}
          body={product.category?.purchaseGuide}
          items={product.buyGuide?.legacyItems ?? product.category?.purchaseGuideItems}
        />
      )}
    </form>
  );
}

function SchemaField({
  field,
  family,
  formik,
  onChangeExtra,
  onBlurExtra,
  digitsOnly = false,
  stripSpaces = false,
}: {
  field: CatalogInputField;
  family: string;
  formik: ReturnType<typeof useFormik<Record<string, string>>>;
  onChangeExtra: () => void;
  onBlurExtra?: () => void;
  digitsOnly?: boolean;
  stripSpaces?: boolean;
}) {
  const touched = Boolean(formik.touched[field.name]);
  const error = formik.errors[field.name];
  const options = selectOptions(field.options);
  const isSelect = Boolean(options) || /select|dropdown/i.test(field.type);
  const hint = fieldHint(touched, error);
  const previewState = fieldPreviewState(touched, error);
  const value = formik.values[field.name] ?? '';
  const help =
    field.help ||
    (field.name === 'steamLogin'
      ? 'همان نامی که با آن وارد Steam می‌شوید، نه نام نمایشی'
      : field.name === 'invite_url'
        ? 'Steam ← Friends ← Add a Friend ← Create Invite Link'
        : null);
  const placeholder =
    field.name === 'invite_url' ? 'https://s.team/p/xxxx-xxxx' : field.name === 'telegram_username' ? '@username' : field.label;

  function changeField(next: string) {
    let value = next;
    if (digitsOnly) value = onlyDigits(value);
    else if (stripSpaces) value = value.replace(/\s+/g, '');
    if (field.name === 'telegram_username') value = value.replace(/^@+/, '');
    void formik.setFieldValue(field.name, value);
    onChangeExtra();
  }

  if (isSelect && options) {
    return (
      <div data-field={field.name}>
        <SelectField
          label={field.required ? `${field.label} *` : field.label}
          hint={hint || help || undefined}
          previewState={previewState}
          placeholder={field.label}
          variant="shop"
          options={options}
          showItemIcon={false}
          value={value}
          onBlur={() => {
            void formik.setFieldTouched(field.name, true);
            onBlurExtra?.();
          }}
          onChange={changeField}
        />
      </div>
    );
  }

  return (
    <label data-field={field.name} className="flex flex-col gap-2">
      <span className="text-sm text-text">{field.required ? `${field.label} *` : field.label}</span>
      <span className="relative block">
        <input
          {...hotjarSuppress}
          className={`${idFieldClass} ${value ? 'pe-10' : ''} ${
            previewState === 'error' ? 'border-error-200 focus:border-error-200 focus:ring-error-200/20' : ''
          }`}
          placeholder={placeholder}
          value={value}
          inputMode={digitsOnly ? 'numeric' : undefined}
          autoComplete="off"
          dir={family === 'steam-gift' || field.name === 'invite_url' ? 'ltr' : undefined}
          onBlur={() => {
            void formik.setFieldTouched(field.name, true);
            onBlurExtra?.();
          }}
          onChange={(event) => changeField(event.target.value)}
        />
        {value ? (
          <button
            type="button"
            aria-label="پاک کردن"
            onClick={() => changeField('')}
            className="absolute end-2 top-1/2 inline-flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-text/55 hover:bg-text/5 hover:text-text"
          >
            <CloseIcon size={16} />
          </button>
        ) : null}
      </span>
      {help ? <span className="text-xs text-text/55">{help}</span> : null}
      {hint ? <span className="text-xs text-error-200">{hint}</span> : null}
    </label>
  );
}

function InvoiceRow({
  label,
  value,
  emphasis = false,
}: {
  label: string;
  value: string;
  emphasis?: boolean;
}) {
  return (
    <div className="flex items-start justify-between gap-3">
      <dt className="shrink-0 text-text/45">{label}</dt>
      <dd className={`min-w-0 text-end ${emphasis ? 'font-semibold text-primary-600' : 'text-text'}`}>{value}</dd>
    </div>
  );
}
