"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  Element3Icon,
  HeartIcon,
  Home2Icon,
  PeopleIcon,
  ProfileIcon,
} from "@/components/ui/icon";
import { ROUTES } from "@/lib/routes";
import type { AuthUser } from "@/types/auth";

type MobileDockProps = {
  user?: AuthUser | null;
  onSearch?: () => void;
};

function isActive(pathname: string, href: string) {
  if (href === ROUTES.home) return pathname === ROUTES.home;
  return pathname === href || pathname.startsWith(`${href}/`);
}

function slotClass(active: boolean) {
  return [
    "flex h-14 flex-col items-center justify-center gap-0.5 transition-colors duration-200",
    active
      ? "text-primary-600 dark:text-[#00e5ff]"
      : "text-text-muted dark:text-[#607BB2]",
  ].join(" ");
}

const dockShellClass =
  "pointer-events-auto flex h-16 w-full items-center overflow-hidden rounded-t-xl border border-b-0 border-border bg-linear-to-b from-card to-canvas p-px shadow-[0_-6px_20px_rgb(18_32_51/0.08)] dark:border-[#1c3571] dark:from-[#1c3571] dark:to-[#07183F] dark:shadow-[0_-12px_32px_rgb(0_0_0/0.35)]";

const dockGridClass =
  "pointer-events-auto grid h-full w-full grid-cols-5 items-center rounded-[11px] bg-card/95 text-text-muted backdrop-blur-sm dark:bg-radial-[at_50%_0%] dark:from-[#0E3077] dark:to-[#07183F] dark:to-70% dark:text-[#607BB2]";

export function MobileDock({ user }: MobileDockProps) {
  const pathname = usePathname();
  const profileHref = user ? ROUTES.profile : ROUTES.auth;
  const favoritesHref = user ? ROUTES.profileFavorites : ROUTES.auth;
  const shopActive = pathname.startsWith("/catalog");

  const homeActive = isActive(pathname, ROUTES.home);
  const favoritesActive = isActive(pathname, ROUTES.profileFavorites);
  const aboutActive = isActive(pathname, ROUTES.about);
  const profileActive =
    isActive(pathname, ROUTES.profile) || pathname.startsWith("/auth");

  return (
    <nav
      dir="ltr"
      className="pointer-events-none fixed bottom-0 z-[90] w-full pb-[env(safe-area-inset-bottom,0px)] lg:hidden"
      aria-label="منوی موبایل"
    >
      <div className="container mx-auto w-full px-4 xl:max-w-7xl">
        <div className={dockShellClass}>
          <div className={dockGridClass}>
            <Link
              href={ROUTES.home}
              className={slotClass(homeActive)}
              aria-label="خانه"
              aria-current={homeActive ? "page" : undefined}
            >
              <Home2Icon size={24} color="currentColor" />
              <span className="text-xs leading-none">خانه</span>
            </Link>
            <Link
              href={favoritesHref}
              className={slotClass(favoritesActive)}
              aria-label="علاقه‌مندی‌ها"
              aria-current={favoritesActive ? "page" : undefined}
            >
              <HeartIcon size={24} color="currentColor" />
              <span className="text-xs leading-none">علاقه‌مندی‌ها</span>
            </Link>
            <Link
              href={ROUTES.categories}
              className={slotClass(shopActive)}
              aria-label="فروشگاه"
              aria-current={shopActive ? "page" : undefined}
            >
              <Element3Icon size={24} color="currentColor" />
              <span className="text-xs leading-none">فروشگاه</span>
            </Link>
            <Link
              href={ROUTES.about}
              className={slotClass(aboutActive)}
              aria-label="درباره ما"
              aria-current={aboutActive ? "page" : undefined}
            >
              <PeopleIcon size={24} />
              <span className="text-xs leading-none">درباره ما</span>
            </Link>
            <Link
              href={profileHref}
              className={slotClass(profileActive)}
              aria-label="حساب من"
              aria-current={profileActive ? "page" : undefined}
            >
              <ProfileIcon size={24} color="currentColor" />
              <span className="text-xs leading-none">حساب من</span>
            </Link>
          </div>
        </div>
      </div>
    </nav>
  );
}
