import { FlatCompat } from '@eslint/eslintrc';
import reactHooks from 'eslint-plugin-react-hooks';
import simpleImportSort from 'eslint-plugin-simple-import-sort';
import unusedImports from 'eslint-plugin-unused-imports';
import { dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const compat = new FlatCompat({ baseDirectory: __dirname });

export default [
  ...compat.extends('next/core-web-vitals', 'next/typescript'),
  {
    plugins: {
      'react-hooks': reactHooks,
      'unused-imports': unusedImports,
      'simple-import-sort': simpleImportSort
    },
    rules: {
      ...reactHooks.configs.recommended.rules,
      '@typescript-eslint/no-explicit-any': 'off',
      '@typescript-eslint/no-unused-vars': 'off',
      'unused-imports/no-unused-imports': 'warn',
      'simple-import-sort/imports': 'warn',
      'simple-import-sort/exports': 'warn',
      'react-hooks/exhaustive-deps': 'warn',
      'react/self-closing-comp': 'warn',
      'prefer-const': 'warn',
      'object-shorthand': 'warn',
      'prefer-destructuring': 'warn',
      'prefer-template': 'warn'
    }
  }
];
