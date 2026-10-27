type JsonLdData = Record<string, unknown> | Record<string, unknown>[];

type JsonLdProps = {
  data: JsonLdData;
};

const FORBIDDEN_KEYS = new Set(['aggregateRating', 'review', 'reviews']);

function stripForbidden(value: unknown): unknown {
  if (Array.isArray(value)) {
    return value.map(stripForbidden);
  }
  if (value && typeof value === 'object') {
    const next: Record<string, unknown> = {};
    for (const [key, nested] of Object.entries(value as Record<string, unknown>)) {
      if (FORBIDDEN_KEYS.has(key)) {
        continue;
      }
      next[key] = stripForbidden(nested);
    }
    return next;
  }
  return value;
}

export function JsonLd({ data }: JsonLdProps) {
  const json = JSON.stringify(stripForbidden(data)).replace(/</g, '\\u003c');
  return <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: json }} />;
}
