import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  // Laravel backend (php artisan serve). The SPA calls /api and /sanctum on its own origin,
  // so Sanctum's session cookie is first-party (no CORS, no third-party cookies).
  const backend = env.VITE_BACKEND_URL || 'http://127.0.0.1:8000'
  // Optional: the public hostname a Cloudflare Tunnel (or any HTTPS tunnel) serves this dev
  // server on, e.g. vendslip.com. Vite then accepts that Host header, listens on 127.0.0.1 where
  // cloudflared connects, and points hot reload at wss://<host>:443. Leave empty for plain localhost.
  const publicHost = (env.VITE_PUBLIC_HOST || '').trim().replace(/^https?:\/\//, '').replace(/\/.*$/, '')
  const tunnel = publicHost
    ? { host: '127.0.0.1', allowedHosts: [publicHost, `www.${publicHost}`], hmr: { host: publicHost, protocol: 'wss' as const, clientPort: 443 } }
    : {}

  return {
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
    },
    server: {
      port: 5173,
      ...tunnel,
      proxy: {
        '/api': { target: backend, changeOrigin: false },
        '/sanctum': { target: backend, changeOrigin: false },
        '/storage': { target: backend, changeOrigin: false },
      },
    },
    preview: {
      proxy: {
        '/api': { target: backend, changeOrigin: false },
        '/sanctum': { target: backend, changeOrigin: false },
        '/storage': { target: backend, changeOrigin: false },
      },
    },
    build: {
      rollupOptions: {
        output: {
          manualChunks: (id: string) => {
            if (id.includes('node_modules/recharts') || id.includes('node_modules/d3-')) return 'charts'
            if (id.includes('node_modules/react') || id.includes('node_modules/scheduler')) return 'react'
            return undefined
          },
        },
      },
    },
  }
})
