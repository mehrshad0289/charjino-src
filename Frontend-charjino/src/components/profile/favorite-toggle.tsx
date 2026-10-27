'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { addFavorite, getFavoriteStatus, removeFavorite } from '@/actions/favorites.actions';
import { HeartIcon } from '@/components/ui/icon';
import { notify } from '@/lib/toast';
import { authHref } from '@/lib/safe-path';
import { useFavorites } from './favorites-provider';

type FavoriteToggleProps = {
  productId: number;
  returnTo?: string;
  compact?: boolean;
  overlay?: boolean;
};

function currentPath() {
  if (typeof window === 'undefined') return '/';
  return `${window.location.pathname}${window.location.search}`;
}

export function FavoriteToggle({
  productId,
  returnTo,
  compact = false,
  overlay = false,
}: FavoriteToggleProps) {
  const router = useRouter();
  const favorites = useFavorites();
  const [localReady, setLocalReady] = useState(false);
  const [localSignedIn, setLocalSignedIn] = useState(false);
  const [localFavorited, setLocalFavorited] = useState(false);
  const [pending, setPending] = useState(false);

  const usingContext = Boolean(favorites);
  const ready = usingContext ? favorites!.ready : localReady;
  const signedIn = usingContext ? favorites!.signedIn : localSignedIn;
  const favorited = usingContext ? favorites!.isFavorited(productId) : localFavorited;

  useEffect(() => {
    if (usingContext) return;
    let cancelled = false;
    void (async () => {
      const status = await getFavoriteStatus(productId);
      if (cancelled) return;
      if ('needAuth' in status && status.needAuth) {
        setLocalSignedIn(false);
        setLocalFavorited(false);
        setLocalReady(true);
        return;
      }
      setLocalSignedIn(true);
      setLocalFavorited(status.ok ? status.data.favorited : false);
      setLocalReady(true);
    })();
    return () => {
      cancelled = true;
    };
  }, [productId, usingContext]);

  async function toggle() {
    if (pending) return;
    const nextPath = returnTo ?? currentPath();
    if (!signedIn) {
      router.push(authHref(nextPath));
      return;
    }
    setPending(true);
    if (usingContext) {
      const result = await favorites!.toggle(productId);
      setPending(false);
      if (!result.ok) {
        if (result.needAuth) {
          router.push(authHref(nextPath));
          return;
        }
        notify.error(result.message ?? 'ذخیره نشد.');
        return;
      }
      router.refresh();
      return;
    }

    const next = !localFavorited;
    setLocalFavorited(next);
    const result = next ? await addFavorite(productId) : await removeFavorite(productId);
    setPending(false);
    if (!result.ok) {
      setLocalFavorited(!next);
      if ('needAuth' in result && result.needAuth) {
        router.push(authHref(nextPath));
        return;
      }
      notify.error(result.message);
      return;
    }
    router.refresh();
  }

  return (
    <button
      type="button"
      onPointerDown={(event) => event.stopPropagation()}
      onClick={(event) => {
        event.preventDefault();
        event.stopPropagation();
        void toggle();
      }}
      disabled={!ready || pending}
      aria-pressed={favorited}
      aria-label={favorited ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها'}
      className={`inline-flex items-center justify-center rounded-full ring-1 transition-colors shrink-0 ${
        overlay
          ? `size-8 bg-background/80 ring-border/60 ${
              favorited ? 'text-sale [&_svg]:fill-current' : 'text-text/80 hover:text-sale'
            }`
          : `${compact ? 'size-8' : 'size-8'} ring-border ${
              favorited ? 'bg-secondary-50 text-sale [&_svg]:fill-current' : 'bg-paper text-text/55 hover:text-sale'
            }`
      }`}
    >
      <HeartIcon size={compact || overlay ? 16 : 18} />
    </button>
  );
}

export function ProductCardFavorite({ productId }: { productId: number }) {
  return (
    <div
      className="absolute start-2 top-2 z-10"
      onPointerDown={(event) => event.stopPropagation()}
      onClick={(event) => {
        event.preventDefault();
        event.stopPropagation();
      }}
    >
      <FavoriteToggle productId={productId} compact overlay />
    </div>
  );
}
