export type SlipVariant = 'classic' | 'minimal'

export type SlipView = {
  variant: SlipVariant
  docType: 'RECEIPT' | 'INVOICE' | 'CREDIT_NOTE'
  docNumber: string
  status: string
  businessName: string
  businessMeta?: string | null
  logoUrl?: string | null
  primary: string
  secondary: string
  customerName: string
  customerPhone?: string | null
  customerAddress?: string | null
  issuedAt: string
  dueDate?: string | null
  currency: string
  lines: { description: string; quantity: number; unit_minor: number; total_minor: number; was_minor?: number | null }[]
  subtotal: number
  /** Item discounts already inside the subtotal (list price − selling price). */
  lineDiscount?: number
  discount: number
  tax: number
  taxLabel: string
  shipping: number
  total: number
  paid: number
  balance: number
  verificationCode?: string | null
  watermark: boolean
  bank?: { name: string | null; number: string; accountName: string } | null
  footer?: string | null
}
