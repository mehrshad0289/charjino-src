export type SidebarSubItem = {
  key: string;
  label: string;
  path: string;
};

export type SidebarIconName = 'grid' | 'page' | 'chat' | 'user' | 'calendar' | 'list' | 'table';

export type SidebarNavItem = {
  key: string;
  label: string;
  path?: string;
  icon: SidebarIconName;
  subItems?: SidebarSubItem[];
};

export type SidebarMenuType = 'main' | 'support' | 'others';
