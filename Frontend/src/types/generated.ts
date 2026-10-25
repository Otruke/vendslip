export type AuditSeverity = 'INFO' | 'WARNING' | 'CRITICAL';
export type BillingCycle = 'MONTHLY' | 'YEARLY';
export type Channel = 'WHATSAPP' | 'TELEGRAM' | 'INSTAGRAM' | 'FACEBOOK' | 'WEB';
export type CursorPaginatedDataCollection<TKey, TValue> = CursorPaginator<TKey, TValue>;
export type CursorPaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
path: string,
per_page: number,
next_cursor: string | null,
next_page_url: string | null,
prev_cursor: string | null,
prev_page_url: string | null,
},
};
export type CursorPaginatorInterface<TKey, TValue> = CursorPaginator<TKey, TValue>;
export type DocumentDraftData = {
type: DocumentType,
customer_name: string,
items: DocumentItemData[],
customer_phone: string | null,
customer_email: string | null,
customer_address: string | null,
customer_id: string | null,
discount_minor: number,
shipping_minor: number,
vat_rate_bps: number | null,
vat_inclusive: boolean | null,
amount_paid_minor: number | null,
due_date: string | null,
notes: string | null,
template_id: number | null,
use_paid_credit: boolean,
};
export type DocumentItemData = {
description: string,
quantity: number,
unit_price_minor: number,
sku_id: string | null,
};
export type DocumentStatus = 'DRAFT' | 'UNPAID' | 'PARTIAL' | 'PAID' | 'OVERDUE' | 'VOID';
export type DocumentType = 'RECEIPT' | 'INVOICE' | 'CREDIT_NOTE';
export type DraftSessionStatus = 'COLLECTING' | 'AWAITING_CONFIRMATION' | 'CONFIRMED' | 'CANCELLED' | 'EXPIRED';
export type LengthAwarePaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
total: number,
current_page: number,
first_page_url: string,
from: number | null,
last_page: number,
last_page_url: string,
next_page_url: string | null,
path: string,
per_page: number,
prev_page_url: string | null,
to: number | null,
},
};
export type LengthAwarePaginatorInterface<TKey, TValue> = LengthAwarePaginator<TKey, TValue>;
export type MerchantStatus = 'ACTIVE' | 'SUSPENDED';
export type MutabilityMode = 'IMMUTABLE' | 'MUTABLE_WITH_AUDIT' | 'MUTABLE_UNRESTRICTED';
export type PaginatedDataCollection<TKey, TValue> = LengthAwarePaginator<TKey, TValue>;
export type PaymentGateway = 'FLUTTERWAVE' | 'NOWPAYMENTS' | 'MANUAL';
export type PaymentMethod = 'CASH' | 'BANK_TRANSFER' | 'CARD' | 'POS' | 'FLUTTERWAVE' | 'CRYPTO' | 'OTHER';
export type PaymentPurpose = 'SUBSCRIPTION' | 'CREDIT_PACK' | 'INVOICE_PAYMENT';
export type PaymentStatus = 'PENDING' | 'SUCCESSFUL' | 'FAILED' | 'CANCELLED';
export type Permission = 'documents.view' | 'documents.issue' | 'documents.edit' | 'documents.void' | 'payments.record' | 'customers.manage' | 'inventory.manage' | 'team.manage' | 'settings.manage' | 'billing.manage' | 'channels.manage' | 'reports.view';
export type PlanTier = 'FREE' | 'PRO' | 'BUSINESS_PLUS';
export type Role = 'SuperAdmin' | 'Admin' | 'Owner' | 'Manager' | 'SalesAgent' | 'Accountant';
