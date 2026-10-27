/** @type {import('next').NextConfig} */
const backendOrigin = (process.env.BACKEND_URL || 'http://127.0.0.1:3000/api').replace(/\/+$/, '').replace(/\/api$/i, '');

const nextConfig = {
  reactStrictMode: true,
  compress: true,
  experimental: {
    serverActions: {
      bodySizeLimit: '24mb',
      allowedOrigins: ['admin.charjino.ir', 'admin-front-charjino.vercel.app']
    }
  },
  async rewrites() {
    return [
      {
        source: '/storage/:path*',
        destination: `${backendOrigin}/storage/:path*`
      }
    ];
  },
  images: {
    remotePatterns: [
      { protocol: 'https', hostname: 'api.charjino.ir' },
      { protocol: 'https', hostname: '**' },
      { protocol: 'http', hostname: '127.0.0.1', port: '3000' },
      { protocol: 'http', hostname: 'localhost', port: '3000' }
    ]
  }
};

export default nextConfig;
