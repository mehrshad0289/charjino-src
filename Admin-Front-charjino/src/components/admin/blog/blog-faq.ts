export type BlogFaqPair = { question: string; answer: string };

export function parseFaqField(value: string | BlogFaqPair[] | null | undefined): BlogFaqPair[] {
  if (Array.isArray(value)) {
    return value
      .map((item) => pair(item.question, item.answer))
      .filter((item): item is BlogFaqPair => item !== null);
  }
  if (!value?.trim()) return [];
  const trimmed = value.trim();
  if (trimmed.startsWith('[')) {
    try {
      const parsed = JSON.parse(trimmed) as unknown;
      if (Array.isArray(parsed)) return parseFaqField(parsed as BlogFaqPair[]);
    } catch {
      // fall through to line parser
    }
  }
  return trimmed
    .split(/\r\n|\n|\r/)
    .map((line) => parseFaqLine(line))
    .filter((item): item is BlogFaqPair => item !== null);
}

export function formatFaqField(items: BlogFaqPair[] | null | undefined): string {
  if (!items?.length) return '';
  return items.map((item) => `${item.question} | ${item.answer}`).join('\n');
}

export function parseFaqLine(line: string): BlogFaqPair | null {
  const trimmed = line.trim();
  if (!trimmed) return null;
  let question = '';
  let answer = '';
  if (trimmed.includes('||')) {
    const parts = trimmed.split('||');
    question = parts[0] ?? '';
    answer = parts.slice(1).join('||');
  } else if (trimmed.includes('|')) {
    const parts = trimmed.split('|');
    question = parts[0] ?? '';
    answer = parts.slice(1).join('|');
  } else {
    return null;
  }
  return pair(question, answer);
}

function pair(question: string, answer: string): BlogFaqPair | null {
  const q = question.replace(/<[^>]+>/g, '').trim();
  const a = answer.replace(/<[^>]+>/g, '').trim();
  if (!q || !a) return null;
  return { question: q.slice(0, 180), answer: a.slice(0, 1000) };
}
