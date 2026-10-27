import type { ColorScale } from './scales-core';

export const STATUS_SCALES: ColorScale[] = [
  {
    name: 'Success',
    token: 'success',
    description: 'Success colors have a positive emotion. These colors are used for success or complete states.',
    shades: {
      25: '#f6fef9',
      50: '#ecfdf3',
      100: '#d1fadf',
      200: '#a6f4c5',
      300: '#6ce9a6',
      400: '#32d583',
      500: '#12b76a',
      600: '#039855',
      700: '#027a48',
      800: '#05603a',
      900: '#054f31',
      950: '#053321'
    }
  },
  {
    name: 'Error',
    token: 'error',
    description:
      'Red color often has a negative connotation. This is the reason why all errors have a red color. Error color indicates something danger.',
    shades: {
      25: '#fffbfa',
      50: '#fef3f2',
      100: '#fee4e2',
      200: '#fecdca',
      300: '#fda29b',
      400: '#f97066',
      500: '#f04438',
      600: '#d92d20',
      700: '#b42318',
      800: '#912018',
      900: '#7a271a',
      950: '#55160c'
    }
  },
  {
    name: 'Warning',
    token: 'warning',
    description: 'Warning colors have the feeling of caution. These colors mostly are used for warning states.',
    shades: {
      25: '#fffcf5',
      50: '#fffaeb',
      100: '#fef0c7',
      200: '#fedf89',
      300: '#fec84b',
      400: '#fdb022',
      500: '#f79009',
      600: '#dc6803',
      700: '#b54708',
      800: '#93370d',
      900: '#7a2e0e',
      950: '#4e1d09'
    }
  }
];
