export function getBackendUrl(): string {
  const raw = process.env.BACKEND_URL?.trim();
  if (!raw) {
    throw new Error('BACKEND_URL is not set');
  }

  return raw.replace(/\/+$/, '');
}
