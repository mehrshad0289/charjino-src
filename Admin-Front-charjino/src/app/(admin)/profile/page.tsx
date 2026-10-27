import { getSessionUser } from '@/actions/auth.actions';
import { ProfileScreen } from '@/components/profile';
import { PageBreadcrumb } from '@/components/ui/page-breadcrumb';
import { PROFILE_COPY } from '@/data/profile';

export default async function ProfilePage() {
  const user = await getSessionUser();

  return (
    <div>
      <PageBreadcrumb pageTitle={PROFILE_COPY.title} />
      <ProfileScreen email={user?.email} />
    </div>
  );
}
