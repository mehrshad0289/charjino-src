function div(a: number, b: number) {
  return Math.trunc(a / b);
}

const GREGORIAN_MONTH_DAYS = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
const JALALI_MONTH_DAYS = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

export function jalaliToGregorian(jy: number, jm: number, jd: number) {
  const jy0 = jy - 979;
  const jm0 = jm - 1;
  const jd0 = jd - 1;
  let jDayNo = 365 * jy0 + div(jy0, 33) * 8 + div((jy0 % 33) + 3, 4);
  for (let i = 0; i < jm0; i += 1) jDayNo += JALALI_MONTH_DAYS[i];
  jDayNo += jd0;

  let gDayNo = jDayNo + 79;
  const gy = 1600 + 400 * div(gDayNo, 146097);
  gDayNo %= 146097;

  let leap = true;
  if (gDayNo >= 36525) {
    gDayNo -= 1;
    const cycles = div(gDayNo, 36524);
    gDayNo %= 36524;
    if (gDayNo >= 365) gDayNo += 1;
    else leap = false;
    return finishGregorian(gy + 100 * cycles, gDayNo, leap);
  }

  return finishGregorian(gy, gDayNo, leap);
}

function finishGregorian(gy: number, gDayNo: number, leap: boolean) {
  let year = gy + 4 * div(gDayNo, 1461);
  gDayNo %= 1461;
  let isLeap = leap;

  if (gDayNo >= 366) {
    isLeap = false;
    gDayNo -= 1;
    year += div(gDayNo, 365);
    gDayNo %= 365;
  }

  let month = 0;
  while (gDayNo >= GREGORIAN_MONTH_DAYS[month] + (month === 1 && isLeap ? 1 : 0)) {
    gDayNo -= GREGORIAN_MONTH_DAYS[month] + (month === 1 && isLeap ? 1 : 0);
    month += 1;
  }

  return { gy: year, gm: month + 1, gd: gDayNo + 1 };
}

export function gregorianToJalali(gy: number, gm: number, gd: number) {
  const gy0 = gy - 1600;
  const gm0 = gm - 1;
  const gd0 = gd - 1;
  let gDayNo = 365 * gy0 + div(gy0 + 3, 4) - div(gy0 + 99, 100) + div(gy0 + 399, 400);
  for (let i = 0; i < gm0; i += 1) gDayNo += GREGORIAN_MONTH_DAYS[i];
  if (gm0 > 1 && ((gy0 % 4 === 0 && gy0 % 100 !== 0) || gy0 % 400 === 0)) gDayNo += 1;
  gDayNo += gd0;

  let jDayNo = gDayNo - 79;
  const jNp = div(jDayNo, 12053);
  jDayNo %= 12053;
  let jy = 979 + 33 * jNp + 4 * div(jDayNo, 1461);
  jDayNo %= 1461;
  if (jDayNo >= 366) {
    jy += div(jDayNo - 1, 365);
    jDayNo = (jDayNo - 1) % 365;
  }

  let month = 0;
  while (month < 11 && jDayNo >= JALALI_MONTH_DAYS[month]) {
    jDayNo -= JALALI_MONTH_DAYS[month];
    month += 1;
  }

  return { jy, jm: month + 1, jd: jDayNo + 1 };
}

export function jalaliToday() {
  const now = new Date();
  return gregorianToJalali(now.getFullYear(), now.getMonth() + 1, now.getDate());
}

export function daysInJalaliMonth(year: number, monthIndex: number) {
  if (monthIndex < 6) return 31;
  if (monthIndex < 11) return 30;
  const esfand1 = jalaliToGregorian(year, 12, 1);
  const next = jalaliToGregorian(year + 1, 1, 1);
  const start = Date.UTC(esfand1.gy, esfand1.gm - 1, esfand1.gd);
  const end = Date.UTC(next.gy, next.gm - 1, next.gd);
  return Math.round((end - start) / 86400000);
}

export function jalaliWeekStart(year: number, month: number) {
  const { gy, gm, gd } = jalaliToGregorian(year, month, 1);
  return (new Date(gy, gm - 1, gd).getDay() + 1) % 7;
}

export type JalaliCell = {
  day: number;
  monthIndex: number;
  year: number;
  muted: boolean;
};

export function jalaliMonthGrid(year: number, monthIndex: number): JalaliCell[] {
  const days = daysInJalaliMonth(year, monthIndex);
  const start = jalaliWeekStart(year, monthIndex + 1);
  const prevMonthIndex = monthIndex === 0 ? 11 : monthIndex - 1;
  const prevYear = monthIndex === 0 ? year - 1 : year;
  const prevDays = daysInJalaliMonth(prevYear, prevMonthIndex);
  const nextMonthIndex = monthIndex === 11 ? 0 : monthIndex + 1;
  const nextYear = monthIndex === 11 ? year + 1 : year;
  const cells: JalaliCell[] = [];

  for (let i = 0; i < start; i += 1) {
    cells.push({
      day: prevDays - start + 1 + i,
      monthIndex: prevMonthIndex,
      year: prevYear,
      muted: true,
    });
  }

  for (let day = 1; day <= days; day += 1) {
    cells.push({ day, monthIndex, year, muted: false });
  }

  const rest = (7 - (cells.length % 7)) % 7;
  for (let day = 1; day <= rest; day += 1) {
    cells.push({
      day,
      monthIndex: nextMonthIndex,
      year: nextYear,
      muted: true,
    });
  }

  return cells;
}

export function jalaliYearList(minYear = 1300, maxYear?: number) {
  const today = jalaliToday();
  const end = maxYear ?? today.jy + 5;
  return Array.from({ length: end - minYear + 1 }, (_, index) => end - index);
}

function pad2(value: number) {
  return String(value).padStart(2, '0');
}

export function isoToJalaliParts(iso: string) {
  const match = iso.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (!match) return null;
  const jalali = gregorianToJalali(
    Number(match[1]),
    Number(match[2]),
    Number(match[3]),
  );
  return { year: jalali.jy, monthIndex: jalali.jm - 1, day: jalali.jd };
}

export function formatJalaliIso(iso?: string | null) {
  if (!iso) return '—';
  const parts = isoToJalaliParts(iso.slice(0, 10));
  if (!parts) return iso.slice(0, 10);
  return `${parts.day}/${parts.monthIndex + 1}/${parts.year}`;
}

export function jalaliPartsToIso(year: number, monthIndex: number, day: number) {
  const gregorian = jalaliToGregorian(year, monthIndex + 1, day);
  return `${gregorian.gy}-${pad2(gregorian.gm)}-${pad2(gregorian.gd)}`;
}
