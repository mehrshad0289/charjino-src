import path from 'node:path';
import { pathToFileURL } from 'node:url';

const SRC = path.resolve(process.cwd(), 'src');

export async function resolve(specifier, context, nextResolve) {
  let next = specifier;
  if (specifier.startsWith('@/')) {
    next = pathToFileURL(path.join(SRC, specifier.slice(2))).href;
  }
  const tryOnce = async (id) => {
    try {
      return await nextResolve(id, context);
    } catch {
      return null;
    }
  };
  const direct = await tryOnce(next);
  if (direct) return direct;
  for (const ext of ['.ts', '.tsx', '.js', '.mjs']) {
    if (next.endsWith(ext)) continue;
    const hit = await tryOnce(`${next}${ext}`);
    if (hit) return hit;
  }
  return nextResolve(next, context);
}
