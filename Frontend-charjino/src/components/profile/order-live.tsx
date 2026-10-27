'use client';

import { useEffect, useState } from 'react';
import { getMyOrder, refreshOrderStatus } from '@/actions/checkout.actions';
import { Button } from '@/components/ui';
import { notify } from '@/lib/toast';
import type { CustomerOrder } from '@/types/catalog';
import { OrderDelivery } from './order-delivery';

export function OrderLive({ order: initial }: { order: CustomerOrder }) {
  const [order, setOrder] = useState(initial);
  const [stale, setStale] = useState(false);

  useEffect(() => {
    setOrder(initial);
  }, [initial]);

  useEffect(() => {
    const seconds = order.pollHintSeconds;
    if (!seconds) return;
    const started = Date.now();
    let timer: number | undefined;

    function tick() {
      if (document.hidden) return;
      if (Date.now() - started > 10 * 60 * 1000) {
        setStale(true);
        return;
      }
      void getMyOrder(order.number).then((result) => {
        if (result.ok) setOrder(result.data);
      });
    }

    function onVisibility() {
      if (!document.hidden) tick();
    }

    timer = window.setInterval(tick, seconds * 1000);
    document.addEventListener('visibilitychange', onVisibility);
    return () => {
      if (timer) window.clearInterval(timer);
      document.removeEventListener('visibilitychange', onVisibility);
    };
  }, [order.number, order.pollHintSeconds]);

  async function refresh() {
    const queued = await refreshOrderStatus(order.number);
    if (!queued.ok) {
      notify.error(queued.message);
      return;
    }
    const fresh = await getMyOrder(order.number);
    if (fresh.ok) setOrder(fresh.data);
  }

  return (
    <>
      {stale ? (
        <p className="text-sm text-text/70">سفارش در حال انجام است؛ وضعیت را بعداً بررسی کنید.</p>
      ) : null}
      {order.pollHintSeconds ? (
        <div className="mb-3">
          <Button type="button" variant="outline" size="M" onClick={() => void refresh()}>
            بررسی دوباره
          </Button>
        </div>
      ) : null}
      <OrderDelivery order={order} />
    </>
  );
}
