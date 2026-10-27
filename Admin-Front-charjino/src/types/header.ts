export type HeaderNotificationStatus = 'success' | 'error';

export type HeaderNotification = {
  key: string;
  name: string;
  avatar: string;
  status: HeaderNotificationStatus;
  action: string;
  target: string;
  category: string;
  time: string;
};

export type HeaderUser = {
  displayName: string;
  fullName: string;
  email: string;
  avatar: string;
};
