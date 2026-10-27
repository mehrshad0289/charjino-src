export type FieldState = 'default' | 'hover' | 'disabled' | 'error';

export type FieldProps = {
  label?: string;
  hint?: string;
  previewState?: FieldState;
  value?: string;
  name?: string;
  compact?: boolean;
  onChange?: (value: string) => void;
  onBlur?: () => void;
};
