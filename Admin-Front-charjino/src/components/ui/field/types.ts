export type FieldState = 'default' | 'hover' | 'disabled' | 'error' | 'success';

export type FieldProps = {
  label?: string;
  hint?: string;
  previewState?: FieldState;
  value?: string;
  name?: string;
  onChange?: (value: string) => void;
  onBlur?: () => void;
};
