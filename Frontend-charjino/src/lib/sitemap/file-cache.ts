import { mkdirSync, readFileSync, renameSync, writeFileSync } from 'fs';
import path from 'path';
import { tmpdir } from 'os';

export type CachedChunk = {
  generatedAt: number;
  locs: string[];
  lastmods?: Array<string | null>;
  excluded: number;
  lastError?: string | null;
  meta?: {
    productsTotal?: number;
    steamGiftsTotal?: number;
  };
};

const memory = new Map<string, CachedChunk>();

function cacheDir() {
  const fromEnv = process.env.SITEMAP_CACHE_DIR?.trim();
  if (fromEnv) {
    return fromEnv;
  }
  try {
    const dir = path.join(process.cwd(), '.sitemap-cache');
    mkdirSync(dir, { recursive: true });
    return dir;
  } catch {
    return path.join(tmpdir(), 'charjino-sitemap-cache');
  }
}

function fileFor(key: string) {
  return path.join(cacheDir(), `${key}.json`);
}

export function readChunk(key: string): CachedChunk | null {
  const fromMemory = memory.get(key);
  try {
    const parsed = JSON.parse(readFileSync(fileFor(key), 'utf8')) as CachedChunk;
    if (!parsed || !Array.isArray(parsed.locs) || typeof parsed.generatedAt !== 'number') {
      return fromMemory ?? null;
    }
    memory.set(key, parsed);
    return parsed;
  } catch {
    return fromMemory ?? null;
  }
}

export function writeChunk(key: string, chunk: CachedChunk): void {
  memory.set(key, chunk);
  const file = fileFor(key);
  const tmp = `${file}.${process.pid}.tmp`;
  try {
    mkdirSync(path.dirname(file), { recursive: true });
    writeFileSync(tmp, JSON.stringify(chunk));
    renameSync(tmp, file);
  } catch {
    try {
      writeFileSync(file, JSON.stringify(chunk));
    } catch {
      /* in-memory copy remains */
    }
  }
}

export function markStale(key: string): void {
  const current = readChunk(key);
  if (!current) {
    return;
  }
  writeChunk(key, { ...current, generatedAt: 0 });
}
