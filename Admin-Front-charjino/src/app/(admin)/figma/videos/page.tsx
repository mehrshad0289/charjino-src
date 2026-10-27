import { PageBreadcrumb } from '@/components/ui/page-breadcrumb';
import { VideosExample } from '@/components/ui/video';

export default function VideosPage() {
  return (
    <div>
      <PageBreadcrumb pageTitle='ویدیوها' />
      <VideosExample />
    </div>
  );
}
