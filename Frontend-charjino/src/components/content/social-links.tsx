import { SOCIAL } from '@/lib/social';

function InstagramIcon() {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
      <defs>
        <linearGradient id="igGradient" x1="0" y1="1" x2="1" y2="0">
          <stop offset="0%" stopColor="#FFB347" />
          <stop offset="45%" stopColor="#F02D75" />
          <stop offset="100%" stopColor="#7B39D8" />
        </linearGradient>
      </defs>
      <rect x="3" y="3" width="94" height="94" rx="27" fill="url(#igGradient)" />
      <rect x="23" y="23" width="54" height="54" rx="16" fill="none" stroke="#fff" strokeWidth="6" />
      <circle cx="50" cy="50" r="13" fill="none" stroke="#fff" strokeWidth="6" />
      <circle cx="68" cy="32" r="4" fill="#fff" />
    </svg>
  );
}

function TelegramIcon() {
  return (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
      <circle cx="50" cy="50" r="48" fill="#229ED9" />
      <path
        d="M75.7 27.2 65.5 75.3c-.8 3.4-2.8 4.2-5.7 2.6L44 66.1l-7.6 7.3c-.8.8-1.4 1.4-2.9 1.4l1.1-16.2 29.5-26.7c1.3-1.2-.3-1.8-2-.6L25.6 54.2 9.8 49.3c-3.4-1.1-3.4-3.4.7-5L72.3 20.5c2.9-1.1 5.4.7 3.4 6.7Z"
        fill="#fff"
      />
    </svg>
  );
}

const items = [
  { ...SOCIAL.instagram, icon: InstagramIcon },
  { ...SOCIAL.telegram, icon: TelegramIcon },
];

export function SocialLinks() {
  return (
    <div className="grid gap-3 grid-cols-2">
      {items.map((item) => (
        <a
          key={item.href + item.title}
          href={item.href}
          target="_blank"
          rel="noopener noreferrer"
          className="flex items-center gap-3 rounded-2xl bg-card px-4 py-3 ring-1 ring-border transition-colors hover:ring-primary-600"
        >
          <span className="size-10 shrink-0 ">
            <item.icon />
          </span>
          <span className="min-w-0">
            <span className="block text-sm font-semibold text-text">{item.title}</span>
            <span className="block text-xs text-text/60" dir="ltr">
              {item.handle}
            </span>
          </span>
        </a>
      ))}
    </div>
  );
}
