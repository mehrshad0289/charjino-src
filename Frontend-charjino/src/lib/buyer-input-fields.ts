import type { CatalogInputField, CatalogProduct, PurchaseSchema, PurchaseSchemaField } from '@/types/catalog';
import { tomanPrice } from '@/lib/price';

const FORBIDDEN = /pass|otp|pin|login/i;

function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}

function compactKey(name: string) {
  return name.toLowerCase().replace(/[-_]/g, '');
}

export function isForbiddenBuyerField(name: string) {
  const compact = compactKey(name);
  if (compact === 'steamlogin') return false;
  return FORBIDDEN.test(name) || compact === 'email';
}

export function purchaseSchemaOf(product: CatalogProduct | null | undefined): PurchaseSchema | null {
  if (!product?.purchaseSchema || !isRecord(product.purchaseSchema)) return null;
  return product.purchaseSchema as PurchaseSchema;
}

export function schemaType(product: CatalogProduct | null | undefined): string {
  return purchaseSchemaOf(product)?.type || product?.fulfillmentType || '';
}

function fieldFromSchema(field: PurchaseSchemaField): CatalogInputField | null {
  const key = String(field.key ?? field.name ?? '').trim();
  if (!key || isForbiddenBuyerField(key)) return null;
  return {
    name: key,
    label: String(field.labelFa || field.label || key),
    type: String(field.type || 'text'),
    required: field.required !== false,
    options: field.options,
    help: field.help ?? null,
    pattern: field.pattern ?? null,
  };
}

function fromUnknown(item: unknown): CatalogInputField | null {
  if (typeof item === 'string' && item.trim() && !isForbiddenBuyerField(item)) {
    return { name: item.trim(), label: item.trim(), type: 'text', required: true };
  }
  if (!isRecord(item)) return null;
  const name = String(item.name ?? item.key ?? item.field_name ?? '').trim();
  if (!name || isForbiddenBuyerField(name)) return null;
  const label = String(item.labelFa ?? item.label ?? item.title ?? '').trim();
  return {
    name,
    label: label || name,
    type: String(item.type ?? item.field_type ?? 'text') || 'text',
    required: Boolean(item.required ?? item.is_required ?? true),
    options: item.options ?? item.values,
    help: typeof item.help === 'string' ? item.help : null,
    pattern: typeof item.pattern === 'string' ? item.pattern : null,
  };
}

export function resolveInputFields(product: CatalogProduct): CatalogInputField[] {
  const schema = purchaseSchemaOf(product);
  if (schema && Array.isArray(schema.fields)) {
    return schema.fields.map(fieldFromSchema).filter((field): field is CatalogInputField => Boolean(field));
  }
  if (product.giftProxy) return [];
  const fromApi = Array.isArray(product.inputFields)
    ? product.inputFields.map(fromUnknown).filter((field): field is CatalogInputField => Boolean(field))
    : [];
  if (fromApi.length > 0) return fromApi;
  const fallback: unknown[] = [
    ...(Array.isArray(product.inputs) ? product.inputs : []),
    ...(Array.isArray(product.requirements) ? product.requirements : []),
  ];
  const unique = new Map<string, CatalogInputField>();
  for (const item of fallback) {
    const field = fromUnknown(item);
    if (field && !unique.has(field.name)) unique.set(field.name, field);
  }
  return [...unique.values()];
}

export function toRequirements(
  schema: PurchaseSchema | null,
  values: Record<string, string>,
): Record<string, string> {
  const out: Record<string, string> = {};
  const keys = schema?.fields?.map((field) => String(field.key ?? field.name ?? '').trim()).filter(Boolean);
  const source = keys && keys.length > 0 ? keys : Object.keys(values);
  for (const key of source) {
    if (isForbiddenBuyerField(key)) continue;
    const value = String(values[key] ?? '').trim();
    if (value) out[key] = value;
  }
  if ((schema?.type || '') === 'steam-topup') {
    if (values.currency?.trim()) out.currency = values.currency.trim();
    if (values.amount?.trim()) out.amount = values.amount.trim();
  }
  return out;
}

/** @deprecated Use toRequirements(schema, values) — keys are not renamed. */
export function toValidateRequirements(values: Record<string, string>): Record<string, string> {
  return toRequirements(null, values);
}

export function selectOptions(options: unknown): { value: string; label: string }[] | null {
  if (!Array.isArray(options) || options.length === 0) return null;
  const mapped = options
    .map((item) => {
      if (typeof item === 'string' || typeof item === 'number') {
        const value = String(item);
        return { value, label: value };
      }
      if (!isRecord(item)) return null;
      const value = String(item.value ?? item.name ?? item.label ?? '').trim();
      if (!value) return null;
      const label = String(item.label ?? item.name ?? item.value ?? value).trim();
      return { value, label: label || value };
    })
    .filter((item): item is { value: string; label: string } => Boolean(item));
  return mapped.length > 0 ? mapped : null;
}

export function sellPriceToman(sku: { price?: number | string | null; costUsd?: unknown; liveCostUsd?: unknown }) {
  void sku.costUsd;
  void sku.liveCostUsd;
  return tomanPrice(sku.price);
}

export function quantityEnabled(product: CatalogProduct): boolean {
  const schema = purchaseSchemaOf(product);
  if (schema?.quantity?.enabled) return true;
  const type = schemaType(product);
  return type === 'giftcard' || type === 'gamekey';
}

export function validationMode(product: CatalogProduct): string {
  return purchaseSchemaOf(product)?.validation?.mode || (product.validateMode === 'api' ? 'fazer' : 'none');
}
