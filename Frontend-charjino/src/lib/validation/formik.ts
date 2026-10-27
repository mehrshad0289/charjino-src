import type { FormEvent } from 'react';
import type { FormikErrors, FormikProps, FormikTouched } from 'formik';
import type { FieldState } from '@/components/ui/field/types';

export function flattenFormikErrors(
  errors: Record<string, unknown>,
  prefix = '',
): string[] {
  const paths: string[] = [];

  for (const [key, value] of Object.entries(errors)) {
    const path = prefix ? `${prefix}.${key}` : key;
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      paths.push(...flattenFormikErrors(value as Record<string, unknown>, path));
      continue;
    }
    if (value) paths.push(path);
  }

  return paths;
}

export function buildTouchedFromErrors<T>(errors: FormikErrors<T>): FormikTouched<T> {
  const touched = {} as FormikTouched<T>;

  for (const [key, value] of Object.entries(errors as Record<string, unknown>)) {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      (touched as Record<string, unknown>)[key] = buildTouchedFromErrors(
        value as FormikErrors<unknown>,
      );
    } else {
      (touched as Record<string, unknown>)[key] = true;
    }
  }

  return touched;
}

export function nestedStringError(bag: unknown, key: string) {
  if (!bag || typeof bag !== 'object') return undefined;
  const value = (bag as Record<string, unknown>)[key];
  return typeof value === 'string' ? value : undefined;
}

export function nestedTouched(bag: unknown, key: string) {
  if (!bag || typeof bag !== 'object') return false;
  return Boolean((bag as Record<string, unknown>)[key]);
}

export function buildTouchedFromPaths(paths: string[]) {
  const touched: Record<string, unknown> = {};

  for (const path of paths) {
    const parts = path.split('.').filter(Boolean);
    let cursor = touched;
    for (let index = 0; index < parts.length; index += 1) {
      const part = parts[index];
      if (index === parts.length - 1) {
        cursor[part] = true;
        break;
      }
      if (!cursor[part] || typeof cursor[part] !== 'object') {
        cursor[part] = {};
      }
      cursor = cursor[part] as Record<string, unknown>;
    }
  }

  return touched;
}

export function fieldHasError(
  touched: boolean | undefined,
  error: string | undefined,
) {
  return Boolean(touched && error);
}

export function fieldHint(
  touched: boolean | undefined,
  error: string | undefined,
  fallback = '',
) {
  return fieldHasError(touched, error) ? (error as string) : fallback;
}

export function fieldPreviewState(
  touched: boolean | undefined,
  error: string | undefined,
): FieldState {
  return fieldHasError(touched, error) ? 'error' : 'default';
}

function resolveScrollTarget(fieldPath: string): HTMLElement | null {
  const selectors = [
    `[data-field="${fieldPath}"]`,
    `[name="${fieldPath}"]`,
    `#field-${fieldPath}`,
  ];

  for (const selector of selectors) {
    const element = document.querySelector<HTMLElement>(selector);
    if (element) return element;
  }

  return null;
}

export function scrollToFirstFormikError(
  errors: Record<string, unknown>,
  fieldOrder?: string[],
) {
  if (typeof window === 'undefined') return;

  const errorKeys = flattenFormikErrors(errors);
  if (!errorKeys.length) return;

  const firstField =
    fieldOrder?.find((field) => errorKeys.includes(field)) ?? errorKeys[0];
  const target = resolveScrollTarget(firstField);
  if (!target) return;

  window.requestAnimationFrame(() => {
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    const focusable = target.querySelector<HTMLElement>(
      'input:not([type="hidden"]), select, textarea, button',
    );
    (focusable ?? target).focus?.({ preventScroll: true });
  });
}

export function stepHasFormikError(
  errors: Record<string, unknown>,
  stepFields: string[],
) {
  const errorPaths = flattenFormikErrors(errors);
  return stepFields.some((field) =>
    errorPaths.some(
      (path) => path === field || path.startsWith(`${field}.`) || field.startsWith(`${path}.`),
    ),
  );
}

export function createFormikSubmitHandler<T>(
  formik: FormikProps<T>,
  options: {
    fieldOrder?: string[];
    onValidSubmit?: () => void | Promise<void>;
  } = {},
) {
  return async (event?: FormEvent<HTMLFormElement>) => {
    event?.preventDefault();
    const errors = await formik.validateForm();
    if (Object.keys(errors).length > 0) {
      formik.setTouched(buildTouchedFromErrors(errors), true);
      scrollToFirstFormikError(errors as Record<string, unknown>, options.fieldOrder);
      return;
    }
    if (options.onValidSubmit) {
      await options.onValidSubmit();
      return;
    }
    await formik.submitForm();
  };
}
