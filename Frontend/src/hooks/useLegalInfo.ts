import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'

export type LegalInfo = { version: string; in_force: boolean; summary: string | null; entity: string; address: string; privacy_email: string; support_email: string }

/** Used until /public/legal answers (and if it can't). Keep in step with config/vendslip.php. */
const FALLBACK: LegalInfo = {
  version: '2026-11-08', in_force: true, summary: null, entity: 'Techberryz Futurecore Limited', address: 'Lagos, Nigeria',
  privacy_email: 'privacy@vendslip.com', support_email: 'support@vendslip.com',
}

export function useLegalInfo(): LegalInfo {
  const { data } = useQuery({ queryKey: ['legal-info'], queryFn: async () => (await api.get<LegalInfo>('/public/legal')).data, staleTime: 5 * 60_000 })
  return data ?? FALLBACK
}
