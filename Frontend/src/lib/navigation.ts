/** Only allow same-site relative redirects (prevents open-redirect via ?next=). */
export function safeNext(next: string | null): string {
  return next && next.startsWith('/') && !next.startsWith('//') && !next.startsWith('/\\') ? next : '/dashboard'
}
