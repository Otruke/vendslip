# NetSlip Frontend

React 19 + Vite + TypeScript + Tailwind v4: the public site (landing, pricing, verify, pay)
and the merchant dashboard.

```bash
npm install
npm run dev        # http://localhost:5173, proxies /api /sanctum /storage to the backend
npm run build      # production bundle in dist/
npx tsc -b && npx eslint .
```

The backend address comes from `VITE_BACKEND_URL` (default `http://127.0.0.1:8000`; see `.env.example`).
Types in `src/types/generated.ts` come from PHP: `cd ../Backend && php artisan typescript:transform`.
