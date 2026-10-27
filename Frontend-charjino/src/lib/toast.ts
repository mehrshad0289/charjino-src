import { toast } from 'sonner';

export const notify = {
  success: (message: string) => toast.success(message),
  error: (message: string) => toast.error(message),
  warning: (message: string) => toast.warning(message),
  info: (message: string) => toast.info(message),
  loading: (message: string, id?: string) => toast.loading(message, id ? { id } : undefined),
  dismiss: (id?: string) => toast.dismiss(id),
};
