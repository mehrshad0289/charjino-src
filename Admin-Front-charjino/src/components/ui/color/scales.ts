import { COLOR_SCALES as CORE_SCALES } from './scales-core';
import { STATUS_SCALES } from './scales-status';

export { COLOR_STEPS, type ColorScale, type ColorStep } from './scales-core';
export const COLOR_SCALES = [...CORE_SCALES, ...STATUS_SCALES];
