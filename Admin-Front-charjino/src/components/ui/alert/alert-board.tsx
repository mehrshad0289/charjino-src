import { Alert, type AlertVariant } from './alert';

const ITEMS: { variant: AlertVariant; title: string; message: string }[] = [
  {
    variant: 'success',
    title: 'Success Message',
    message: 'Be cautious when performing this action.'
  },
  {
    variant: 'error',
    title: 'Error Message',
    message: 'Be cautious when performing this action.'
  },
  {
    variant: 'warning',
    title: 'Warning Message',
    message: 'Be cautious when performing this action.'
  },
  {
    variant: 'info',
    title: 'Info Message',
    message: 'Be cautious when performing this action.'
  }
];

export function AlertBoard() {
  return (
    <div className='flex min-w-0 flex-col gap-4'>
      {ITEMS.map((item) => (
        <Alert key={item.variant} variant={item.variant} title={item.title} message={item.message} showLink />
      ))}
    </div>
  );
}
