import axios, { AxiosError } from 'axios'

/**
 * Sanctum SPA (cookie) auth. In dev, Vite proxies /api and /sanctum to Laravel so
 * everything is same-origin; axios sends the XSRF-TOKEN cookie back as X-XSRF-TOKEN.
 */
export const api = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  timeout: 30_000,
})

let csrfReady: Promise<unknown> | null = null
export function ensureCsrf(): Promise<unknown> {
  csrfReady ??= axios.get('/sanctum/csrf-cookie', { withCredentials: true }).catch((e) => {
    csrfReady = null
    throw e
  })
  return csrfReady
}

/** Re-fetch the CSRF cookie (after login/logout the session is regenerated). */
export function resetCsrf(): void {
  csrfReady = null
}

api.interceptors.request.use(async (config) => {
  const method = (config.method ?? 'get').toLowerCase()
  if (!['get', 'head', 'options'].includes(method)) await ensureCsrf()
  return config
})

export class ApiError extends Error {
  status: number
  code: string | null
  fieldErrors: Record<string, string[]>

  constructor(message: string, status: number, code: string | null, fieldErrors: Record<string, string[]> = {}) {
    super(message)
    this.status = status
    this.code = code
    this.fieldErrors = fieldErrors
  }

  firstFieldError(field: string): string | undefined {
    return this.fieldErrors[field]?.[0]
  }
}

type ErrorBody = { message?: string; code?: string; errors?: Record<string, string[]> }

let onUnauthorized: (() => void) | null = null
export function setUnauthorizedHandler(handler: () => void): void {
  onUnauthorized = handler
}

/** Called when the server refuses a change because newer Terms must be accepted first. */
let onTermsRequired: (() => void) | null = null
export function setTermsRequiredHandler(handler: () => void): void {
  onTermsRequired = handler
}

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError<ErrorBody>) => {
    const status = error.response?.status ?? 0
    const body = error.response?.data
    if (status === 419) resetCsrf() // CSRF token mismatch: next write fetches a fresh cookie
    if (status === 401 && onUnauthorized && !error.config?.url?.includes('/auth/')) onUnauthorized()
    if (status === 409 && body?.code === 'terms_update_required') onTermsRequired?.()
    const message =
      body?.message && body.message !== 'Server Error'
        ? body.message
        : status === 0
          ? 'Can’t connect. Check your internet connection and try again.'
          : status === 502 || status === 503 || status === 504
            ? 'VendSlip can’t be reached right now. Please try again in a moment.'
            : status === 429
              ? 'Too many attempts. Please wait a minute and try again.'
              : 'Something went wrong. Please try again.'
    return Promise.reject(new ApiError(message, status, body?.code ?? null, body?.errors ?? {}))
  },
)

export function errorMessage(error: unknown): string {
  return error instanceof Error ? error.message : 'Something went wrong.'
}
