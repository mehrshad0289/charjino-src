import Link from 'next/link';
import { BrandWordmark } from '@/components/brand';
import { Container } from '@/components/ui';
import { APP_NAME } from '@/lib/constants';
import { FOOTER_COLUMNS } from '@/data/navigation';
import { toFaDigits } from '@/lib/fa';
import { SocialLinks } from '@/components/content/social-links';
import { EnamadSeal } from '@/components/layout/enamad-seal';
import { ROUTES } from '@/lib/routes';
import type { AuthUser } from '@/types/auth';

type SiteFooterProps = {
  user?: AuthUser | null;
};

function footerHref(href: string, user?: AuthUser | null) {
  if (href === ROUTES.profile && !user) {
    return ROUTES.auth;
  }
  return href;
}

export function SiteFooter({ user = null }: SiteFooterProps) {
  return (
    <div className="w-full bg-footer">
      <Container className="flex flex-col items-center py-12">
        <div className="flex w-full flex-col gap-5 mb-5">
          <div className="grid w-full grid-cols-2 gap-8 text-sm font-normal sm:grid-cols-4">
            {FOOTER_COLUMNS.map((column) => (
              <div key={column.title} className="flex flex-col gap-6">
                <div className="font-semibold leading-6 text-primary-600">{column.title}</div>
                <div className="flex flex-col gap-4">
                  {column.items.map((item) => {
                    const href = footerHref(item.href, user);
                    const external = href.startsWith('http');
                    const className = 'leading-6 transition-all hover:text-primary-600/75';
                    return external ? (
                      <a key={item.title} href={href} target="_blank" rel="noopener noreferrer" className={className}>
                        {item.title}
                      </a>
                    ) : (
                      <Link key={item.title} href={href} className={className}>
                        {item.title}
                      </Link>
                    );
                  })}
                </div>
              </div>
            ))}
          </div>
        </div>
        <div className="w-full space-y-1 border-t border-border pt-6 text-center text-xs">
          <div className="mb-4 flex justify-center">
            <BrandWordmark />
          </div>
          <div className="mx-auto mb-6 w-full max-w-xl">
            <SocialLinks />
          </div>
          <EnamadSeal />
          <p>{`© ${toFaDigits(1405)} ${APP_NAME}. همه حقوق محفوظ است.`}</p>
          <p>فروشگاه کالای دیجیتال مجاز، بدون رمز حساب</p>
        </div>
      </Container>
    </div>
  );
}
