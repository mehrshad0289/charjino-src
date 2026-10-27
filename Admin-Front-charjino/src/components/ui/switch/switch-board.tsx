'use client';

import { Switch } from './switch';

function handleSwitchChange(checked: boolean) {
  console.log('Switch is now:', checked ? 'ON' : 'OFF');
}

export function SwitchBoard() {
  return (
    <div className='flex min-w-0 flex-col gap-6'>
      <section className='min-w-0'>
        <h2 className='mb-4 text-base font-semibold text-gray-800'>Toggle switch input</h2>
        <div className='flex flex-wrap gap-4'>
          <Switch label='Default' defaultChecked onChange={handleSwitchChange} />
          <Switch label='Checked' defaultChecked onChange={handleSwitchChange} />
          <Switch label='Disabled' disabled />
        </div>
      </section>
      <section className='min-w-0'>
        <div className='flex flex-wrap gap-4'>
          <Switch label='Default' defaultChecked color='gray' onChange={handleSwitchChange} />
          <Switch label='Checked' defaultChecked color='gray' onChange={handleSwitchChange} />
          <Switch label='Disabled' disabled color='gray' />
        </div>
      </section>
    </div>
  );
}
