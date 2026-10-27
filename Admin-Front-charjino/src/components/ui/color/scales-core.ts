export const COLOR_STEPS = [25, 50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] as const;

export type ColorStep = (typeof COLOR_STEPS)[number];

export type ColorScale = {
  name: string;
  token: 'brand' | 'gray' | 'blue-light' | 'orange' | 'success' | 'error' | 'warning';
  description: string;
  shades: Record<ColorStep, string>;
};

export const COLOR_SCALES: ColorScale[] = [
  {
    name: 'Brand',
    token: 'brand',
    description:
      'Primary color palette is used on all interactive elements, like CTAs, links, active states, inputs.',
    shades: {
      25: '#f2f7ff',
      50: '#ecf3ff',
      100: '#dde9ff',
      200: '#c2d6ff',
      300: '#9cb9ff',
      400: '#7592ff',
      500: '#465fff',
      600: '#3641f5',
      700: '#2a31d8',
      800: '#252dae',
      900: '#262e89',
      950: '#161950'
    }
  },
  {
    name: 'Gray',
    token: 'gray',
    description: 'Grey colors are most often used. These colors are used mostly for backgrounds, text, inputs, etc.',
    shades: {
      25: '#fcfcfd',
      50: '#f9fafb',
      100: '#f2f4f7',
      200: '#e4e7ec',
      300: '#d0d5dd',
      400: '#98a2b3',
      500: '#667085',
      600: '#475467',
      700: '#344054',
      800: '#1d2939',
      900: '#101828',
      950: '#0c111d'
    }
  },
  {
    name: 'Blue Light',
    token: 'blue-light',
    description: 'Info colors are used for states with informational character, mostly for info alerts and tags.',
    shades: {
      25: '#f5fbff',
      50: '#f0f9ff',
      100: '#e0f2fe',
      200: '#b9e6fe',
      300: '#7cd4fd',
      400: '#36bffa',
      500: '#0ba5ec',
      600: '#0086c9',
      700: '#026aa2',
      800: '#065986',
      900: '#0b4a6f',
      950: '#062c41'
    }
  },
  {
    name: 'Orange',
    token: 'orange',
    description: 'Orange scale from TailAdmin theme.',
    shades: {
      25: '#fffaf5',
      50: '#fff6ed',
      100: '#ffead5',
      200: '#fddcab',
      300: '#feb273',
      400: '#fd853a',
      500: '#fb6514',
      600: '#ec4a0a',
      700: '#c4320a',
      800: '#9c2a10',
      900: '#7e2410',
      950: '#511c10'
    }
  }
];
