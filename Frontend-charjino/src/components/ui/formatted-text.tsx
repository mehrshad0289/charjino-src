type FormattedTextProps = {
  text?: string | null;
  className?: string;
};

export function FormattedText({ text, className = '' }: FormattedTextProps) {
  const value = text ?? '';
  if (!value) return null;
  return <div className={`whitespace-pre-wrap break-words ${className}`.trim()}>{value}</div>;
}
