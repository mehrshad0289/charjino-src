'use server';

import { revalidatePath } from 'next/cache';
import { ApiError, serverFetch } from '@/lib/server-fetcher';
import { apiPaths } from '@/lib/api-paths';
import type { FavoriteItem, FavoritePage, FavoriteStatus } from '@/types/profile';

function toMessage(error: unknown) {
  if (error instanceof ApiError && error.message) return error.message;
  if (error instanceof Error && error.message) return error.message;
  return 'درخواست انجام نشد';
}

export async function listMyFavorites(page = 1) {
  try {
    const data = await serverFetch<FavoritePage>(apiPaths.favoritesQuery({ page }));
    return { ok: true as const, data };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 401) {
      return { ok: false as const, needAuth: true as const, message: toMessage(error) };
    }
    return { ok: false as const, message: toMessage(error) };
  }
}

export async function listFavoriteIds() {
  try {
    const data = await serverFetch<{ productIds: number[] }>(apiPaths.favoritesQuery({ idsOnly: true }));
    return { ok: true as const, productIds: data.productIds ?? [] };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 401) {
      return { ok: false as const, needAuth: true as const, productIds: [] as number[], message: toMessage(error) };
    }
    return { ok: false as const, productIds: [] as number[], message: toMessage(error) };
  }
}

export async function getFavoriteStatus(productId: number) {
  if (!Number.isInteger(productId) || productId < 1) {
    return { ok: false as const, message: 'محصول نامعتبر است.' };
  }
  try {
    const data = await serverFetch<FavoriteStatus>(apiPaths.favoritesQuery({ productId }));
    return { ok: true as const, data };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 401) {
      return { ok: false as const, needAuth: true as const, favorited: false as const, message: toMessage(error) };
    }
    return { ok: false as const, message: toMessage(error) };
  }
}

export async function addFavorite(productId: number) {
  if (!Number.isInteger(productId) || productId < 1) {
    return { ok: false as const, message: 'محصول نامعتبر است.' };
  }
  try {
    const data = await serverFetch<{ favorited: boolean; item: FavoriteItem }>(apiPaths.favorites, {
      method: 'POST',
      body: JSON.stringify({ productId }),
    });
    revalidatePath('/profile/favorites');
    return { ok: true as const, data };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 401) {
      return { ok: false as const, needAuth: true as const, message: toMessage(error) };
    }
    return { ok: false as const, message: toMessage(error) };
  }
}

export async function removeFavorite(productId: number) {
  if (!Number.isInteger(productId) || productId < 1) {
    return { ok: false as const, message: 'محصول نامعتبر است.' };
  }
  try {
    const data = await serverFetch<FavoriteStatus>(apiPaths.favorite(productId), {
      method: 'DELETE',
    });
    revalidatePath('/profile/favorites');
    return { ok: true as const, data };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 401) {
      return { ok: false as const, needAuth: true as const, message: toMessage(error) };
    }
    return { ok: false as const, message: toMessage(error) };
  }
}
