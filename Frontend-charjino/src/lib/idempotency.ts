/** One-time key for a checkout click. Retries of the same click reuse it; other buyers never share it. */
export function checkoutIdempotencyKey() {
  const uuid =
    typeof globalThis.crypto?.randomUUID === 'function'
      ? globalThis.crypto.randomUUID()
      : `${Date.now().toString(16)}-${Math.random().toString(16).slice(2)}`;
  return `chk-${uuid}`.slice(0, 80);
}
