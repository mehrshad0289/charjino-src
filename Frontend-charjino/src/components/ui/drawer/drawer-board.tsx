'use client';

import { useState } from 'react';
import { Button } from '../button';
import { Drawer } from './drawer';

export function DrawerBoard() {
  const [open, setOpen] = useState(false);

  return (
    <div className="flex flex-col items-start gap-4">
      <Button
        size="M"
        color="primary"
        variant="fill"
        leftIcon={false}
        rightIcon={false}
        onClick={() => setOpen(true)}
      >
        باز کردن دراور
      </Button>
      <Drawer
        open={open}
        title="عنوان دراور"
        onClose={() => setOpen(false)}
        footer={
          <Button
            size="M"
            color="primary"
            variant="fill"
            fullWidth
            leftIcon={false}
            rightIcon={false}
            className="!h-[45px] !min-h-[45px] !font-semibold"
            onClick={() => setOpen(false)}
          >
            بستن
          </Button>
        }
      >
        <p className="font-sans text-sm font-normal leading-5 text-gray-500">
          پوستهٔ سراسری. محتوای فیلتر آگهی در `AdsFilterPanel` است.
        </p>
      </Drawer>
    </div>
  );
}
