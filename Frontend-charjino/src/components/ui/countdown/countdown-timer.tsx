'use client';

import { useEffect, useState } from 'react';
import { toFaDigits } from '@/lib/fa';

type TimeLeft = {
  days: number;
  hours: number;
  minutes: number;
  seconds: number;
};

type CountdownTimerProps = {
  endsAt?: string | null;
};

function defaultTargetDate() {
  const date = new Date();
  date.setDate(date.getDate() + 2);
  date.setHours(23, 59, 59, 0);
  return date;
}

function calculateTimeLeft(targetDate: Date): TimeLeft {
  const difference = targetDate.getTime() - Date.now();
  if (difference <= 0) {
    return { days: 0, hours: 0, minutes: 0, seconds: 0 };
  }

  return {
    days: Math.floor(difference / (1000 * 60 * 60 * 24)),
    hours: Math.floor((difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)),
    minutes: Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60)),
    seconds: Math.floor((difference % (1000 * 60)) / 1000),
  };
}

export function CountdownTimer({ endsAt }: CountdownTimerProps) {
  const [timeLeft, setTimeLeft] = useState<TimeLeft | null>(null);
  const isExpired =
    timeLeft !== null && Object.values(timeLeft).every((value) => value === 0);

  useEffect(() => {
    const parsed = endsAt ? new Date(endsAt) : defaultTargetDate();
    const targetDate = Number.isNaN(parsed.getTime()) ? defaultTargetDate() : parsed;
    const tick = () => setTimeLeft(calculateTimeLeft(targetDate));
    tick();
    const timer = window.setInterval(tick, 1000);
    return () => window.clearInterval(timer);
  }, [endsAt]);

  const timeUnits = [
    { label: 'ثانیه', value: timeLeft?.seconds },
    { label: 'دقیقه', value: timeLeft?.minutes },
    { label: 'ساعت', value: timeLeft?.hours },
    { label: 'روز', value: timeLeft?.days },
  ];

  if (isExpired) {
    return <span className="text-sm font-semibold text-red-500">پایان یافته</span>;
  }

  return (
    <div dir="rtl" className="flex items-start gap-1.5">
      {timeUnits.map(({ label, value }) => (
        <span key={label} className="flex w-9 min-w-9 flex-col items-center gap-1">
          <span className="flex h-7 w-9 min-w-9 items-center justify-center rounded-md text-black bg-text-muted/40 dark:bg-text-muted text-xs font-semibold tabular-nums font-bold">
            {toFaDigits(String(value ?? 0).padStart(2, '0'))}
          </span>
          <span className="text-caption-s leading-none text-text-muted">{label}</span>
        </span>
      ))}
    </div>
  );
}
