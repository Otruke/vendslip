import { Check } from 'lucide-react'
import { PASSWORD_RULES } from '@/lib/password'
import { cn } from '@/lib/cn'

/** Live checklist under a new-password field; each requirement ticks as it's met. */
export function PasswordChecklist({ value, className }: { value: string; className?: string }) {
  return (
    <ul className={cn('mt-1.5 grid grid-cols-1 gap-x-4 gap-y-1 text-xs sm:grid-cols-2', className)} aria-label="Password requirements">
      {PASSWORD_RULES.map((rule) => {
        const ok = rule.test(value)
        return (
          <li key={rule.key} className={cn('flex items-center gap-1.5 transition-colors duration-200', ok ? 'text-tone-settled' : 'text-ink-muted')}>
            <span className={cn('inline-flex size-3.5 items-center justify-center rounded-full border transition-colors duration-200', ok ? 'border-tone-settled bg-tone-settled text-white' : 'border-ink-muted')} aria-hidden>
              {ok && <Check className="size-2.5" strokeWidth={3} />}
            </span>
            <span>{rule.label}</span>
            <span className="sr-only">{ok ? '(done)' : '(missing)'}</span>
          </li>
        )
      })}
    </ul>
  )
}
