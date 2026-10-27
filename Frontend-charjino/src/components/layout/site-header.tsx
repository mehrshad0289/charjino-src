"use client";

import { useEffect, useRef, useState } from "react";
import dynamic from "next/dynamic";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { getSessionUser, logout } from "@/actions/auth.actions";
import { BrandWordmark } from "@/components/brand";
import { MobileDock } from "@/components/layout/mobile-dock";
import { SiteAccountMenu } from "@/components/layout/site-account-menu";
import { ThemeToggle } from "@/components/layout/theme-toggle";
import { Button, Container } from "@/components/ui";
import {
  CallIcon,
  DocumentTextIcon,
  GameIcon,
  Home2Icon,
  MenuIcon,
  NotificationBingIcon,
  PeopleIcon,
  ProfileIcon,
  SearchNormalIcon,
} from "@/components/ui/icon";
import { useOutsideDismiss } from "@/components/ui/field";
import { hotjarSuppress } from "@/lib/hotjar";
import { NAV_ITEMS } from "@/data/navigation";
import { ROUTES } from "@/lib/routes";
import type { AuthUser } from "@/types/auth";

const CatalogSearchModal = dynamic(
  () => import("@/components/layout/catalog-search-modal").then((mod) => mod.CatalogSearchModal),
  { ssr: false },
);

const iconBtnClass =
  "inline-flex items-center justify-center rounded-xl p-2 text-text transition-[background,color,transform] duration-200 hover:bg-white/6 active:scale-94 dark:text-[#607BB2] dark:hover:bg-[#00e5ff]/8 dark:hover:text-[#00e5ff]";

const navLinkClass =
  "inline-flex shrink-0 items-center gap-2 rounded-xl px-3 py-2 font-semibold whitespace-nowrap text-text-muted transition-[color,background,box-shadow] duration-200 hover:bg-white/6 hover:text-text dark:text-[#607BB2] dark:hover:bg-white/5 dark:hover:text-[#C7D0DD]";

const navLinkActiveClass =
  "text-primary-600 bg-primary-600/10 shadow-[inset_0_0_0_1px_rgb(5_99_252/0.25)] dark:bg-[#0E3077]/65 dark:text-[#00e5ff] dark:shadow-[inset_0_0_0_1px_#1c3571,0_0_20px_-6px_rgb(0_229_255/0.45)]";

const searchClass =
  "flex h-11 w-full cursor-text items-center gap-2 rounded-xl border border-border bg-card px-3 text-sm text-text shadow-card transition-[border-color,box-shadow] duration-200 hover:border-primary-600 hover:shadow-[0_0_0_3px_rgb(5_99_252/0.12)] dark:border-[#1C3571] dark:bg-radial-[at_50%_-40%] dark:from-[#0E3077] dark:to-[#07183F] dark:shadow-[inset_0_1px_0_rgb(255_255_255/0.04)] dark:hover:border-[#00e5ff]/45 dark:hover:shadow-[inset_0_1px_0_rgb(255_255_255/0.06),0_0_24px_-8px_rgb(0_229_255/0.35)]";

const searchInputClass =
  "h-full min-w-0 flex-1 cursor-text border-0 bg-transparent text-sm text-text outline-none placeholder:text-text/45 dark:placeholder:text-white/40";

const menuPanelClass =
  "mt-2 w-56 overflow-hidden rounded-2xl border border-border bg-surface p-1 text-sm leading-normal text-text shadow-modal dark:border-[#1c3571] dark:bg-linear-to-b dark:from-[#0E3077] dark:to-[#07183F]";

const menuItemClass =
  "flex w-full items-center gap-2 rounded-[0.625rem] px-3 py-1.5 hover:bg-white/8";

const menuItemActiveClass = "bg-[#00e5ff]/12 text-[#00e5ff]";

const headerFrameClass =
  "border-b border-border bg-nav backdrop-blur-[18px] dark:border-[#1c3571] dark:bg-linear-to-b dark:from-[rgb(14_48_119/0.96)] dark:to-[rgb(7_24_63/0.98)] dark:shadow-[0_1px_0_rgb(28_53_113/0.9),0_12px_40px_-20px_rgb(0_229_255/0.22)]";

function isShopBrowse(pathname: string) {
  return (
    pathname === ROUTES.categories ||
    pathname.startsWith(`${ROUTES.categories}/`)
  );
}

function navIcon(href: string) {
  if (href === ROUTES.home) return Home2Icon;
  if (href === ROUTES.invoices) return SearchNormalIcon;
  if (href === ROUTES.profile) return ProfileIcon;
  if (href === ROUTES.categories) return GameIcon;
  if (href === ROUTES.blog) return DocumentTextIcon;
  if (href === ROUTES.about) return PeopleIcon;
  if (href === ROUTES.contact) return CallIcon;
  if (href === ROUTES.terms) return DocumentTextIcon;
  return GameIcon;
}

function isNavActive(pathname: string, href: string) {
  if (href === ROUTES.home) return pathname === ROUTES.home;
  if (href === ROUTES.categories) {
    return (
      pathname === ROUTES.categories ||
      pathname.startsWith(`${ROUTES.categories}/`)
    );
  }
  if (href === ROUTES.profile) {
    return (
      pathname === ROUTES.profile || pathname.startsWith(`${ROUTES.profile}/`)
    );
  }
  if (href === ROUTES.blog) {
    return pathname === ROUTES.blog || pathname.startsWith(`${ROUTES.blog}/`);
  }
  return pathname === href;
}

function HeaderSearch({
  onOpen,
  compact = false,
}: {
  onOpen: () => void;
  compact?: boolean;
}) {
  return (
    <label
      className={compact ? `${searchClass} max-w-64` : searchClass}
      onMouseDown={(event) => {
        event.preventDefault();
        onOpen();
      }}
    >
      <span className="inline-flex shrink-0 text-primary-600 dark:text-[#00e5ff]">
        <SearchNormalIcon size={18} />
      </span>
      <input
        {...hotjarSuppress}
        type="text"
        readOnly
        placeholder="جستجو در کل فروشگاه..."
        aria-label="جستجو در کل فروشگاه"
        className={searchInputClass}
        onFocus={onOpen}
      />
    </label>
  );
}

type SiteHeaderProps = {
  user?: AuthUser | null;
};

export function SiteHeader({ user: initialUser = null }: SiteHeaderProps) {
  const pathname = usePathname();
  const [user, setUser] = useState<AuthUser | null>(initialUser);
  const [menuOpen, setMenuOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);
  const shopBrowse = isShopBrowse(pathname);

  useEffect(() => {
    setUser(initialUser);
  }, [initialUser]);

  useEffect(() => {
    let cancelled = false;
    void getSessionUser().then((session) => {
      if (!cancelled) {
        setUser(session);
      }
    });
    return () => {
      cancelled = true;
    };
  }, []);

  useOutsideDismiss(menuOpen, menuRef, () => setMenuOpen(false));

  const extraItems = user
    ? [
        { title: "حساب من", href: ROUTES.profile },
        { title: "سفارش‌ها", href: ROUTES.profileOrders },
      ]
    : [{ title: "پیگیری سفارش", href: ROUTES.invoices }];
  const menuItems = [...NAV_ITEMS, ...extraItems];

  return (
    <>
      <header className="relative isolate z-[100]">
        <div className={headerFrameClass}>
          <Container className="font-sans text-sm font-semibold">
            <div className="relative flex h-15 items-center justify-between gap-3">
              <div className="flex items-center lg:hidden">
                <div className="relative" ref={menuRef}>
                  <button
                    type="button"
                    className={iconBtnClass}
                    aria-label="منو"
                    aria-expanded={menuOpen}
                    onClick={() => setMenuOpen((open) => !open)}
                  >
                    <MenuIcon size={24} />
                  </button>
                  {menuOpen ? (
                    <div className={`absolute top-full right-0 z-[110] ${menuPanelClass}`}>
                      {menuItems.map((item) => (
                        <Link
                          key={item.href}
                          href={item.href}
                          className={`${menuItemClass} ${isNavActive(pathname, item.href) ? menuItemActiveClass : ""}`}
                          onClick={() => setMenuOpen(false)}
                        >
                          {item.title}
                        </Link>
                      ))}
                      <div className="my-1 h-px bg-white/8" />
                      <ThemeToggle variant="menu" />
                      {user ? (
                        <form action={logout}>
                          <button type="submit" className={menuItemClass}>
                            خروج
                          </button>
                        </form>
                      ) : (
                        <Link
                          href={ROUTES.auth}
                          className={menuItemClass}
                          onClick={() => setMenuOpen(false)}
                        >
                          ورود
                        </Link>
                      )}
                    </div>
                  ) : null}
                </div>
                <Link href={ROUTES.categories} className={iconBtnClass}>
                  <NotificationBingIcon size={24} />
                  <span className="sr-only">اعلان‌ها</span>
                </Link>
              </div>
              <Link
                href={ROUTES.home}
                className="inline-flex shrink-0 items-center lg:static lg:p-2"
                aria-label="شارژینو، صفحهٔ اصلی"
              >
                <BrandWordmark />
              </Link>
              <div className="hidden min-w-0 flex-1 items-center gap-4 lg:flex lg:mr-4">
                <div className="flex min-w-0 flex-1 items-center gap-1 xl:gap-2">
                  {NAV_ITEMS.map((item) => {
                    const Icon = navIcon(item.href);
                    const active = isNavActive(pathname, item.href);
                    return (
                      <Link
                        key={item.href}
                        href={item.href}
                        className={`${navLinkClass} ${active ? navLinkActiveClass : ""}`}
                      >
                        <Icon size={16} />
                        <span>{item.title}</span>
                      </Link>
                    );
                  })}
                </div>
                {shopBrowse ? (
                  <HeaderSearch compact onOpen={() => setSearchOpen(true)} />
                ) : null}
                <div className="flex shrink-0 items-center gap-2">
                  <ThemeToggle />
                  {user ? (
                    <SiteAccountMenu />
                  ) : (
                    <Button href={ROUTES.auth} variant="outline" className="active:scale-100">
                      ورود
                    </Button>
                  )}
                </div>
              </div>
            </div>
            {!shopBrowse ? (
              <div className="pb-3 lg:max-w-xl">
                <HeaderSearch onOpen={() => setSearchOpen(true)} />
              </div>
            ) : null}
          </Container>
        </div>
      </header>
      <MobileDock user={user} onSearch={() => setSearchOpen(true)} />
      {searchOpen ? <CatalogSearchModal open onClose={() => setSearchOpen(false)} /> : null}
    </>
  );
}
