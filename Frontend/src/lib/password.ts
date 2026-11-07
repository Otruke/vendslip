/**
 * Mirrors the backend's Password::defaults(): 10+ characters with upper and lower case, a number and
 * a symbol. The server stays the authority (it also rejects breached passwords in production).
 */
export const PASSWORD_RULES = [
  { key: 'length', label: '10+ characters', test: (v: string) => v.length >= 10 },
  { key: 'lower', label: 'a lowercase letter', test: (v: string) => /\p{Ll}/u.test(v) },
  { key: 'upper', label: 'an uppercase letter', test: (v: string) => /\p{Lu}/u.test(v) },
  { key: 'number', label: 'a number', test: (v: string) => /\d/.test(v) },
  { key: 'symbol', label: 'a symbol (e.g. ! @ # $)', test: (v: string) => /[^\p{L}\p{N}\s]/u.test(v) },
] as const

export function passwordIssues(value: string): string[] {
  return PASSWORD_RULES.filter((r) => !r.test(value)).map((r) => r.label)
}

export function isStrongPassword(value: string): boolean {
  return value.length <= 128 && passwordIssues(value).length === 0
}
