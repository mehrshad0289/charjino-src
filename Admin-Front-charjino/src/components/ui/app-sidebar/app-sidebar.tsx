'use client';

import {
  SIDEBAR_GROUPS,
  SIDEBAR_NAV_ITEMS,
  SIDEBAR_OTHER_ITEMS,
  SIDEBAR_SUPPORT_ITEMS
} from '@/data/sidebar';
import { useSidebar } from '@/hooks/useSidebar';
import type { SidebarMenuType, SidebarNavItem } from '@/types/sidebar';
import Image from 'next/image';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import {
  CalenderIcon,
  ChatIcon,
  ChevronDownIcon,
  GridIcon,
  HorizontaLDots,
  ListIcon,
  PageIcon,
  TableIcon,
  UserCircleIcon
} from '../icon';

const ICONS = {
  grid: <GridIcon />,
  page: <PageIcon />,
  chat: <ChatIcon />,
  user: <UserCircleIcon />,
  calendar: <CalenderIcon />,
  list: <ListIcon />,
  table: <TableIcon />
};

const SIDEBAR_SECTIONS: { type: SidebarMenuType; title: string; items: SidebarNavItem[] }[] = (
  [
    { type: 'main', title: SIDEBAR_GROUPS.menu, items: SIDEBAR_NAV_ITEMS },
    { type: 'support', title: SIDEBAR_GROUPS.support, items: SIDEBAR_SUPPORT_ITEMS },
    { type: 'others', title: SIDEBAR_GROUPS.others, items: SIDEBAR_OTHER_ITEMS }
  ] as { type: SidebarMenuType; title: string; items: SidebarNavItem[] }[]
).filter((section) => section.items.length > 0);

export function AppSidebar() {
  const { isExpanded, isMobileOpen, isHovered, setIsHovered } = useSidebar();
  const pathname = usePathname();
  const [openSubmenu, setOpenSubmenu] = useState<{ type: SidebarMenuType; index: number } | null>(
    null
  );
  const [subMenuHeight, setSubMenuHeight] = useState<Record<string, number>>({});
  const subMenuRefs = useRef<Record<string, HTMLDivElement | null>>({});

  const allNavPaths = useMemo(() => {
    const paths: string[] = [];
    SIDEBAR_SECTIONS.forEach((section) => {
      section.items.forEach((nav) => {
        if (nav.path) paths.push(nav.path);
        nav.subItems?.forEach((sub) => paths.push(sub.path));
      });
    });
    return paths;
  }, []);

  const isActive = useCallback(
    (path: string) => {
      if (path === '/') return pathname === '/';
      const matches = allNavPaths.filter(
        (candidate) => pathname === candidate || (candidate !== '/' && pathname.startsWith(`${candidate}/`))
      );
      if (matches.length === 0) {
        return path === pathname || pathname.startsWith(`${path}/`);
      }
      const longest = matches.reduce((best, current) => (current.length > best.length ? current : best));
      return longest === path;
    },
    [pathname, allNavPaths]
  );

  useEffect(() => {
    let submenuMatched = false;
    SIDEBAR_SECTIONS.forEach((section) => {
      section.items.forEach((nav, index) => {
        nav.subItems?.forEach((subItem) => {
          if (isActive(subItem.path)) {
            setOpenSubmenu({ type: section.type, index });
            submenuMatched = true;
          }
        });
      });
    });
    if (!submenuMatched) setOpenSubmenu(null);
  }, [pathname, isActive]);

  useEffect(() => {
    if (openSubmenu === null) return;
    const key = `${openSubmenu.type}-${openSubmenu.index}`;
    if (subMenuRefs.current[key]) {
      setSubMenuHeight((prev) => ({
        ...prev,
        [key]: subMenuRefs.current[key]?.scrollHeight || 0
      }));
    }
  }, [openSubmenu]);

  function handleSubmenuToggle(index: number, menuType: SidebarMenuType) {
    setOpenSubmenu((prev) => {
      if (prev && prev.type === menuType && prev.index === index) return null;
      return { type: menuType, index };
    });
  }

  function renderMenuItems(items: SidebarNavItem[], menuType: SidebarMenuType) {
    return (
      <ul className='flex flex-col gap-1'>
        {items.map((nav, index) => (
          <li key={nav.key}>
            {nav.subItems ? (
              <button
                type='button'
                onClick={() => handleSubmenuToggle(index, menuType)}
                className={[
                  'group menu-item cursor-pointer',
                  'menu-item-inactive',
                  !isExpanded && !isHovered ? 'lg:justify-center' : 'lg:justify-start'
                ].join(' ')}
              >
                <span className='menu-item-icon-inactive'>
                  {ICONS[nav.icon]}
                </span>
                {(isExpanded || isHovered || isMobileOpen) && (
                  <span className='menu-item-text'>{nav.label}</span>
                )}
                {(isExpanded || isHovered || isMobileOpen) && (
                  <ChevronDownIcon
                    className={`ms-auto h-5 w-5 transition-transform duration-200 ${
                      openSubmenu?.type === menuType && openSubmenu?.index === index
                        ? 'rotate-180 text-brand-500'
                        : ''
                    }`}
                  />
                )}
              </button>
            ) : (
              nav.path && (
                <Link
                  href={nav.path}
                  className={`group menu-item ${
                    isActive(nav.path) ? 'menu-item-active' : 'menu-item-inactive'
                  }`}
                >
                  <span className={isActive(nav.path) ? 'menu-item-icon-active' : 'menu-item-icon-inactive'}>
                    {ICONS[nav.icon]}
                  </span>
                  {(isExpanded || isHovered || isMobileOpen) && (
                    <span className='menu-item-text'>{nav.label}</span>
                  )}
                </Link>
              )
            )}
            {nav.subItems && (isExpanded || isHovered || isMobileOpen) && (
              <div
                ref={(el) => {
                  subMenuRefs.current[`${menuType}-${index}`] = el;
                }}
                className='overflow-hidden transition-all duration-300'
                style={{
                  height:
                    openSubmenu?.type === menuType && openSubmenu?.index === index
                      ? subMenuHeight[`${menuType}-${index}`]
                        ? `${subMenuHeight[`${menuType}-${index}`]}px`
                        : 'auto'
                      : '0px'
                }}
              >
                <ul className='ms-9 mt-2 space-y-1'>
                  {nav.subItems.map((subItem) => (
                    <li key={subItem.key}>
                      <Link
                        href={subItem.path}
                        className={`menu-dropdown-item ${
                          isActive(subItem.path)
                            ? 'menu-dropdown-item-active'
                            : 'menu-dropdown-item-inactive'
                        }`}
                      >
                        {subItem.label}
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </li>
        ))}
      </ul>
    );
  }

  return (
    <aside
      className={`fixed top-0 left-0 z-50 flex h-full flex-col border-r border-gray-200 bg-white px-5 text-gray-900 transition-all duration-300 ease-in-out xl:mt-0 rtl:right-0 rtl:left-auto rtl:border-r-0 rtl:border-l dark:border-gray-800 dark:bg-gray-900 ${
        isExpanded || isMobileOpen ? 'w-72.5' : isHovered ? 'w-72.5' : 'w-22.5'
      } ${isMobileOpen ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full'} xl:translate-x-0 xl:rtl:translate-x-0`}
      onMouseEnter={() => !isExpanded && setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
    >
      <div
        className={`flex py-8 ${
          !isExpanded && !isHovered ? 'xl:justify-center' : 'justify-start'
        }`}
      >
        <Link href='/'>
          {isExpanded || isHovered || isMobileOpen ? (
            <>
              <Image
                className='dark:hidden'
                src='/images/logo/charjino-logo-admin-light.svg'
                alt='شارژینو'
                width={195}
                height={40}
                priority
              />
              <Image
                className='hidden dark:block'
                src='/images/logo/charjino-logo-admin-dark.svg'
                alt='شارژینو'
                width={186}
                height={40}
                priority
              />
            </>
          ) : (
            <Image
              src='/images/logo/charjino-logo-icon.svg'
              alt='شارژینو'
              width={32}
              height={32}
              priority
            />
          )}
        </Link>
      </div>
      <div className='no-scrollbar flex flex-col overflow-y-auto duration-300 ease-linear'>
        <nav className='mb-6'>
          <div className='flex flex-col gap-4'>
            {SIDEBAR_SECTIONS.map((section) => (
              <div key={section.type}>
                <h2
                  className={`mb-4 flex text-xs leading-5 text-gray-400 uppercase ${
                    !isExpanded && !isHovered ? 'xl:justify-center' : 'justify-start'
                  }`}
                >
                  {isExpanded || isHovered || isMobileOpen ? section.title : <HorizontaLDots />}
                </h2>
                {renderMenuItems(section.items, section.type)}
              </div>
            ))}
          </div>
        </nav>
      </div>
    </aside>
  );
}
