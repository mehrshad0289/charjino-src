export const WEIGHT_VALUE = {
  Bold: 700,
  Semibold: 600,
  Medium: 500,
  Regular: 400
} as const;

export type WeightLabel = keyof typeof WEIGHT_VALUE;

export type TypeStyle = {
  name: string;
  size: number;
  lineHeight: number;
  weight: WeightLabel;
  sample: string;
};

function set(name: string, size: number, lineHeight: number, sample: string): TypeStyle[] {
  return (['Bold', 'Semibold', 'Medium', 'Regular'] as WeightLabel[]).map((weight) => ({
    name: `${name} ${weight}`,
    size,
    lineHeight,
    weight,
    sample
  }));
}

export const TYPE_STYLES: TypeStyle[] = [
  ...set('Title 2xl', 72, 90, 'عنوان ۲xl'),
  ...set('Title xl', 60, 72, 'عنوان xl'),
  ...set('Title lg', 48, 60, 'عنوان lg'),
  ...set('Title md', 36, 44, 'عنوان md'),
  ...set('Title sm', 30, 38, 'عنوان sm'),
  ...set('Theme xl', 20, 30, 'متن xl'),
  ...set('Theme sm', 14, 20, 'متن sm'),
  ...set('Theme xs', 12, 18, 'متن xs')
];
