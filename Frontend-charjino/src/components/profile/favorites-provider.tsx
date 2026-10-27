'use client';

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { addFavorite, listFavoriteIds, removeFavorite } from '@/actions/favorites.actions';
import { getSessionUser } from '@/actions/auth.actions';

type ToggleResult =
  | { ok: true }
  | { ok: false; needAuth?: boolean; message?: string };

type FavoritesContextValue = {
  ready: boolean;
  signedIn: boolean;
  isFavorited: (productId: number) => boolean;
  toggle: (productId: number) => Promise<ToggleResult>;
};

const FavoritesContext = createContext<FavoritesContextValue | null>(null);

export function FavoritesProvider({ children }: { children: ReactNode }) {
  const [ready, setReady] = useState(false);
  const [signedIn, setSignedIn] = useState(false);
  const [ids, setIds] = useState<Set<number>>(new Set());
  const idsRef = useRef(ids);
  idsRef.current = ids;

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      const user = await getSessionUser();
      if (cancelled) return;
      if (!user) {
        setSignedIn(false);
        setIds(new Set());
        setReady(true);
        return;
      }
      const result = await listFavoriteIds();
      if (cancelled) return;
      if ('needAuth' in result && result.needAuth) {
        setSignedIn(false);
        setIds(new Set());
        setReady(true);
        return;
      }
      setSignedIn(true);
      setIds(new Set(result.productIds ?? []));
      setReady(true);
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const isFavorited = useCallback((productId: number) => ids.has(productId), [ids]);

  const toggle = useCallback(async (productId: number): Promise<ToggleResult> => {
    if (!signedIn) {
      return { ok: false, needAuth: true };
    }
    const next = !idsRef.current.has(productId);
    setIds((current) => {
      const copy = new Set(current);
      if (next) copy.add(productId);
      else copy.delete(productId);
      return copy;
    });
    const result = next ? await addFavorite(productId) : await removeFavorite(productId);
    if (!result.ok) {
      setIds((current) => {
        const copy = new Set(current);
        if (next) copy.delete(productId);
        else copy.add(productId);
        return copy;
      });
      return result;
    }
    return { ok: true };
  }, [signedIn]);

  const value = useMemo(
    () => ({ ready, signedIn, isFavorited, toggle }),
    [ready, signedIn, isFavorited, toggle],
  );

  return <FavoritesContext.Provider value={value}>{children}</FavoritesContext.Provider>;
}

export function useFavorites() {
  return useContext(FavoritesContext);
}
