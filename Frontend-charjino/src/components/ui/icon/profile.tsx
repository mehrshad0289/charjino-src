import {
  CloseCircle,
  DiscountShape,
  Edit2,
  KeyboardOpen,
  Logout,
  Refresh,
  Save2,
  SaveAdd,
  Trash,
} from 'iconsax-react';
import { linear, type IconProps } from './types';

export function CloseCircleIcon({ size = 28 }: IconProps) {
  return <CloseCircle size={size} {...linear} />;
}

export function Edit2Icon({ size = 24 }: IconProps) {
  return <Edit2 size={size} {...linear} />;
}

export function KeyboardOpenIcon({ size = 24 }: IconProps) {
  return <KeyboardOpen size={size} {...linear} />;
}

export function Save2Icon({ size = 24 }: IconProps) {
  return <Save2 size={size} {...linear} />;
}

export function SaveAddIcon({ size = 24 }: IconProps) {
  return <SaveAdd size={size} {...linear} />;
}

export function RefreshIcon({ size = 15 }: IconProps) {
  return <Refresh size={size} {...linear} />;
}

export function DiscountShapeIcon({ size = 24 }: IconProps) {
  return <DiscountShape size={size} {...linear} />;
}

export function LogoutIcon({ size = 24 }: IconProps) {
  return <Logout size={size} {...linear} />;
}

export function TrashIcon({ size = 24 }: IconProps) {
  return <Trash size={size} {...linear} />;
}

export function SparklesIcon({ size = 24 }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" aria-hidden>
      <path
        d="M12 3.2c.14 0 .21.08.25.2.36 1.9.34 1.94 2.4 2.32.14.02.22.1.22.23s-.08.21-.22.23c-2.04.4-1.98.44-2.4 2.32-.04.12-.11.2-.25.2s-.21-.08-.25-.2c-.41-1.88-.34-1.93-2.4-2.32-.13-.02-.22-.1-.22-.23s.09-.21.22-.23c2.04-.4 1.98-.44 2.4-2.32.04-.12.11-.2.25-.2Z"
        fill="currentColor"
      />
      <path
        d="M7.2 10.4c.2 0 .34-.12.36-.3.38-2.7.48-2.7 3.4-3.24.19-.03.32-.15.32-.34 0-.18-.13-.31-.32-.34-2.9-.38-3-.48-3.4-3.24-.02-.18-.16-.31-.36-.31s-.33.13-.35.32c-.35 2.72-.5 2.71-3.4 3.23-.19.04-.32.16-.32.34 0 .2.13.31.36.34 2.88.45 3 .52 3.35 3.22.02.2.16.32.36.32ZM14.4 20.6c.27 0 .47-.19.52-.46.75-5.55 1.56-6.4 7.26-7 .3-.03.5-.24.5-.5s-.2-.5-.5-.54c-5.7-.6-6.51-1.45-7.26-7-.05-.27-.25-.46-.52-.46s-.47.19-.51.46c-.75 5.55-1.57 6.4-7.26 7-.3.04-.5.25-.5.54s.2.47.5.5c5.68.72 6.47 1.46 7.26 7 .04.27.24.46.51.46Z"
        fill="currentColor"
      />
    </svg>
  );
}
