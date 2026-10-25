import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type {
  ChannelsResponse, Customer, Doc, DocDetail, Merchant, Overview, Paginated, PublicInvoice, PublicPlans, Revision,
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
export const usePlans = () => useQuery({ queryKey: ['plans'], queryFn: () => get<PublicPlans>('/public/plans'), staleTime: 5 * 60_000 })
export const useVerify = (code: string | undefined) =>
  useQuery({ queryKey: ['verify', code], queryFn: () => get<VerifyResult>(`/public/verify/${encodeURIComponent(code ?? '')}`), enabled: !!code, retry: false })
export const usePublicInvoice = (uid: string | undefined) =>
  useQuery({ queryKey: ['invoice', uid], queryFn: () => get<PublicInvoice>(`/public/invoices/${uid}`), enabled: !!uid, retry: false })

/* ── Session ────────────────────────────────────────────────────────── */
export const fetchSession = () => get<Session>('/auth/me')

/* ── Dashboard ──────────────────────────────────────────────────────── */
export const useOverview = (range: string) =>
  useQuery({ queryKey: ['overview', range], queryFn: () => get<Overview>('/dashboard/overview', { range }), placeholderData: keepPreviousData })

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
