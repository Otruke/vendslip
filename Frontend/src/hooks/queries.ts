import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useCallback, useState } from 'react'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type {
  ActivityResponse, AlertSettings, ChannelsResponse, Customer, Doc, DocDetail, Locales, Merchant, NotificationsResponse, Overview, PayoutAccountsResponse, ReminderSettings, SupportTicket, SupportTicketList, Paginated, PublicInvoice, PublicPlans, Revision,
  Session, ShareLinks, Sku, TeamResponse, Template, Transaction, VerifyResult,
} from '@/types/api'

const get = async <T,>(url: string, params?: Record<string, unknown>) => (await api.get<T>(url, { params })).data

/** Drop empty filters; send booleans as 1 (Laravel's `boolean` rule rejects the string "true"). */
function clean(params: Record<string, unknown>): Record<string, unknown> {
  return Object.fromEntries(
    Object.entries(params)
      .filter(([, v]) => v !== undefined && v !== null && v !== '' && v !== false)
      .map(([k, v]) => [k, v === true ? 1 : v]),
  )
}

/* ── Public ─────────────────────────────────────────────────────────── */
/** Every currency and country (bundled on the server, rarely changes). */
export const useLocales = () => useQuery({ queryKey: ['locales'], queryFn: () => get<Locales>('/public/locales'), staleTime: Infinity, gcTime: Infinity })
export const usePlans = (currency?: string | null) =>
  useQuery({ queryKey: ['plans', currency ?? null], queryFn: () => get<PublicPlans>('/public/plans', clean({ currency })), staleTime: 5 * 60_000, placeholderData: keepPreviousData })

const CURRENCY_KEY = 'vendslip.pricing-currency'

/**
 * The currency to show prices in: the business's own when signed in; otherwise the visitor's pick,
 * or a guess from their browser's region (en-NG → NGN). Null shows the base price.
 */
export function useDisplayCurrency(): [string | null, (code: string) => void] {
  const merchantCurrency = useAuthStore((s) => s.session?.merchant?.currency ?? null)
  const locales = useLocales()
  const [picked, setPicked] = useState<string | null>(() => {
    try { return window.localStorage.getItem(CURRENCY_KEY) } catch { return null }
  })
  const pick = useCallback((code: string) => {
    setPicked(code)
    try { window.localStorage.setItem(CURRENCY_KEY, code) } catch { /* private mode */ }
  }, [])
  if (merchantCurrency) return [merchantCurrency, pick]
  if (picked) return [picked, pick]
  const region = (navigator.languages ?? [navigator.language]).map((l) => l.split('-')[1]?.toUpperCase()).find(Boolean)
  const guess = region ? locales.data?.countries.find((c) => c.code === region)?.currency ?? null : null
  return [guess, pick]
}
export const useVerify = (code: string | undefined) =>
  useQuery({ queryKey: ['verify', code], queryFn: () => get<VerifyResult>(`/public/verify/${encodeURIComponent(code ?? '')}`), enabled: !!code, retry: false })
export const usePublicInvoice = (uid: string | undefined) =>
  useQuery({ queryKey: ['invoice', uid], queryFn: () => get<PublicInvoice>(`/public/invoices/${uid}`), enabled: !!uid, retry: false })
export const usePublicSlip = (code: string | undefined) =>
  useQuery({ queryKey: ['slip', code], queryFn: () => get<PublicInvoice>(`/public/s/${code}`), enabled: !!code, retry: false })

/* ── Session ────────────────────────────────────────────────────────── */
export const fetchSession = () => get<Session>('/auth/me')

/* ── Dashboard ──────────────────────────────────────────────────────── */
/** The bell: checks every minute while the tab is open. */
export const useNotifications = () =>
  useQuery({ queryKey: ['notifications'], queryFn: () => get<NotificationsResponse>('/notifications'), refetchInterval: 60_000, refetchIntervalInBackground: false, staleTime: 20_000 })
export const usePayoutAccounts = () => useQuery({ queryKey: ['payout-accounts'], queryFn: () => get<PayoutAccountsResponse>('/settings/payout-accounts') })
export const useReminderSettings = () => useQuery({ queryKey: ['reminder-settings'], queryFn: () => get<ReminderSettings>('/settings/reminders') })
export const useActivity = (filters: { category?: string; page?: number }, enabled = true) =>
  useQuery({ queryKey: ['activity', filters], queryFn: () => get<ActivityResponse>('/activity', clean(filters)), placeholderData: keepPreviousData, enabled })
export const useSupportTickets = () => useQuery({ queryKey: ['support-tickets'], queryFn: () => get<SupportTicketList>('/support/tickets') })
export const useSupportTicket = (reference: string) =>
  useQuery({ queryKey: ['support-ticket', reference], queryFn: () => get<{ data: SupportTicket }>(`/support/tickets/${encodeURIComponent(reference)}`), refetchInterval: 60_000 })
export const useAlertSettings = () => useQuery({ queryKey: ['alert-settings'], queryFn: () => get<AlertSettings>('/settings/alerts') })

export const useOverview = (range: string, currency?: string | null) =>
  useQuery({ queryKey: ['overview', range, currency ?? null], queryFn: () => get<Overview>('/dashboard/overview', clean({ range, currency })), placeholderData: keepPreviousData })

export type DocumentFilters = { type?: string; status?: string; channel?: string; q?: string; customer_id?: string; outstanding?: boolean; page?: number; per_page?: number; from?: string; to?: string }
export const useDocuments = (filters: DocumentFilters) =>
  useQuery({
    queryKey: ['documents', filters],
    queryFn: () => get<Paginated<Doc>>('/documents', clean(filters)),
    placeholderData: keepPreviousData,
  })
export const useDocument = (id: string | null) =>
  useQuery({ queryKey: ['document', id], queryFn: () => get<DocDetail>(`/documents/${id}`), enabled: !!id })
export const useRevisions = (id: string | null, enabled: boolean) =>
  useQuery({ queryKey: ['revisions', id], queryFn: () => get<{ data: Revision[] }>(`/documents/${id}/revisions`), enabled: !!id && enabled })
export const useShareLinks = (id: string | null, enabled: boolean) =>
  useQuery({ queryKey: ['share', id], queryFn: () => get<ShareLinks>(`/documents/${id}/share`), enabled: !!id && enabled, staleTime: 10 * 60_000 })

export const useCustomers = (params: { q?: string; debtors?: boolean; sort?: string; page?: number }) =>
  useQuery({
    queryKey: ['customers', params],
    queryFn: () => get<Paginated<Customer>>('/customers', clean(params)),
    placeholderData: keepPreviousData,
  })
export const useCustomer = (id: string | null) =>
  useQuery({ queryKey: ['customer', id], queryFn: () => get<{ data: Customer; documents: Doc[]; summary: { total_billed_minor: number; outstanding_minor: number } }>(`/customers/${id}`), enabled: !!id })

export const useInventory = (params: { q?: string; low_stock?: boolean; page?: number }, enabled = true) =>
  useQuery({
    queryKey: ['inventory', params],
    queryFn: () => get<Paginated<Sku>>('/inventory', clean(params)),
    placeholderData: keepPreviousData,
    enabled,
  })

export const useTemplates = () => useQuery({ queryKey: ['templates'], queryFn: () => get<{ data: Template[]; defaults: { receipt: number | null; invoice: number | null } }>('/templates'), staleTime: 60_000 })
export const useTeam = () => useQuery({ queryKey: ['team'], queryFn: () => get<TeamResponse>('/team') })
export const useChannels = () => useQuery({ queryKey: ['channels'], queryFn: () => get<ChannelsResponse>('/channels') })
export const useBilling = () => useQuery({ queryKey: ['billing'], queryFn: () => get<{ transactions: Transaction[]; gateways: { flutterwave: boolean; nowpayments: boolean } }>('/billing') })
export const useTransaction = (reference: string | null) =>
  useQuery({
    queryKey: ['transaction', reference],
    queryFn: () => get<Transaction>(`/billing/transactions/${reference}`),
    enabled: !!reference,
    refetchInterval: (q) => (q.state.data?.status === 'PENDING' ? 4000 : false),
  })

/* ── Mutations ──────────────────────────────────────────────────────── */
export function useInvalidate() {
  const client = useQueryClient()
  return (...keys: string[]) => Promise.all(keys.map((key) => client.invalidateQueries({ queryKey: [key] })))
}

export function useApiMutation<TVars, TResult = unknown>(fn: (vars: TVars) => Promise<TResult>, invalidate: string[] = []) {
  const refresh = useInvalidate()
  return useMutation({
    mutationFn: fn,
    onSuccess: async () => {
      if (invalidate.length) await refresh(...invalidate)
    },
  })
}

export const apiPost = async <T,>(url: string, body?: unknown) => (await api.post<T>(url, body)).data
export const apiPut = async <T,>(url: string, body?: unknown) => (await api.put<T>(url, body)).data
export const apiDelete = async <T,>(url: string, body?: unknown) => (await api.delete<T>(url, { data: body })).data

export type { Merchant }

/** Re-pull /auth/me (usage, credits, plan) after anything that changes them. */
export function useRefreshSession() {
  const setSession = useAuthStore((s) => s.setSession)
  return async () => setSession(await fetchSession())
}
