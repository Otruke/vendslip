import { ChevronDown } from 'lucide-react'
import { useId, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react'
import { cn } from '@/lib/cn'
import { InfoTip } from './InfoTip'

const fieldBase =
  'block w-full rounded-lg border bg-white text-sm text-ink-primary placeholder:text-ink-muted transition focus:outline-none focus:ring-2 focus:ring-vendslip-primary/20 disabled:bg-surface-subtle disabled:text-ink-muted'
const stateClasses = (error?: string) => (error ? 'border-tone-open focus:border-tone-open' : 'border-slate-300 focus:border-vendslip-primary')

export function Field({ id, label, error, helperText, info, children, className, labelAction }: { id: string; label?: ReactNode; error?: string; helperText?: ReactNode; info?: ReactNode; children: ReactNode; className?: string; labelAction?: ReactNode }) {
  return (
    <div className={className}>
      {label && (
        <div className="mb-1.5 flex items-center justify-between gap-2">
          <span className="flex min-w-0 items-center gap-1.5">
            <label htmlFor={id} className="block text-sm font-medium text-ink-primary">{label}</label>
            {info && <InfoTip label={`About ${typeof label === 'string' ? label : 'this field'}`}>{info}</InfoTip>}
          </span>
          {labelAction}
        </div>
      )}
      {children}
      {error ? (
        <p id={`${id}-error`} role="alert" className="mt-1 text-xs text-tone-open">{error}</p>
      ) : helperText ? (
        <p className="mt-1 break-words text-xs text-ink-muted">{helperText}</p>
      ) : null}
    </div>
  )
}

type InputProps = InputHTMLAttributes<HTMLInputElement> & {
  label?: ReactNode
  error?: string
  helperText?: ReactNode
  /** Explanation behind a small "i" next to the label (instead of text under the field). */
  info?: ReactNode
  prefixIcon?: ReactNode
  suffix?: ReactNode
  wrapperClassName?: string
  labelAction?: ReactNode
  ref?: React.Ref<HTMLInputElement>
}

export function Input({ label, error, helperText, info, prefixIcon, suffix, className, wrapperClassName, id, labelAction, ref, ...rest }: InputProps) {
  const autoId = useId()
  const inputId = id ?? autoId
  return (
    <Field id={inputId} label={label} error={error} helperText={helperText} info={info} className={wrapperClassName} labelAction={labelAction}>
      <div className="relative">
        {prefixIcon && <span className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-ink-muted [&>svg]:size-4">{prefixIcon}</span>}
        <input
          ref={ref}
          id={inputId}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? `${inputId}-error` : undefined}
          className={cn(fieldBase, stateClasses(error), 'h-10 px-3', prefixIcon && 'pl-9', suffix && 'pr-12', className)}
          {...rest}
        />
        {suffix && <span className="absolute inset-y-0 right-3 flex items-center text-xs text-ink-muted">{suffix}</span>}
      </div>
    </Field>
  )
}

type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & { label?: ReactNode; error?: string; helperText?: ReactNode; info?: ReactNode; wrapperClassName?: string; ref?: React.Ref<HTMLTextAreaElement> }

export function Textarea({ label, error, helperText, info, className, wrapperClassName, id, ref, ...rest }: TextareaProps) {
  const autoId = useId()
  const inputId = id ?? autoId
  return (
    <Field id={inputId} label={label} error={error} helperText={helperText} info={info} className={wrapperClassName}>
      <textarea ref={ref} id={inputId} aria-invalid={error ? true : undefined} className={cn(fieldBase, stateClasses(error), 'px-3 py-2', className)} {...rest} />
    </Field>
  )
}

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
  label?: ReactNode
  error?: string
  helperText?: ReactNode
  info?: ReactNode
  options: { value: string; label: string; disabled?: boolean }[]
  placeholder?: string
  wrapperClassName?: string
  ref?: React.Ref<HTMLSelectElement>
}

export function Select({ label, error, helperText, info, options, placeholder, className, wrapperClassName, id, ref, ...rest }: SelectProps) {
  const autoId = useId()
  const inputId = id ?? autoId
  return (
    <Field id={inputId} label={label} error={error} helperText={helperText} info={info} className={wrapperClassName}>
      <div className="relative">
        <select ref={ref} id={inputId} aria-invalid={error ? true : undefined} className={cn(fieldBase, stateClasses(error), 'h-10 appearance-none pl-3 pr-9', className)} {...rest}>
          {placeholder !== undefined && <option value="">{placeholder}</option>}
          {options.map((o) => <option key={o.value} value={o.value} disabled={o.disabled}>{o.label}</option>)}
        </select>
        <ChevronDown className="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-ink-muted" aria-hidden />
      </div>
    </Field>
  )
}

export function ToggleSwitch({ checked, onChange, label, description, info, disabled, id }: { checked: boolean; onChange: (value: boolean) => void; label: ReactNode; description?: ReactNode; info?: ReactNode; disabled?: boolean; id?: string }) {
  const autoId = useId()
  const switchId = id ?? autoId
  return (
    <div className="flex items-start justify-between gap-4">
      <div className="min-w-0">
        <span className="flex items-center gap-1.5">
          <label htmlFor={switchId} className="cursor-pointer text-sm font-medium text-ink-primary">{label}</label>
          {info && <InfoTip label={`About ${typeof label === 'string' ? label : 'this setting'}`}>{info}</InfoTip>}
        </span>
        {description && <label htmlFor={switchId} className="mt-0.5 block cursor-pointer text-xs text-ink-secondary">{description}</label>}
      </div>
      <button
        id={switchId}
        type="button"
        role="switch"
        aria-checked={checked}
        disabled={disabled}
        onClick={() => onChange(!checked)}
        className={cn('relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full transition disabled:opacity-50', checked ? 'bg-vendslip-primary' : 'bg-slate-300')}
      >
        <span className={cn('inline-block size-5 rounded-full bg-white shadow transition', checked ? 'translate-x-5.5' : 'translate-x-0.5')} />
      </button>
    </div>
  )
}

export function Checkbox({ label, className, ...rest }: InputHTMLAttributes<HTMLInputElement> & { label: ReactNode }) {
  return (
    <label className={cn('flex cursor-pointer items-center gap-2 text-sm text-ink-primary', className)}>
      <input type="checkbox" className="size-4 rounded border-slate-300 accent-vendslip-primary" {...rest} />
      {label}
    </label>
  )
}

/** A message for the whole form (e.g. "Couldn't sign in"): plain small red text, like a field's error. */
export function FormError({ children, className }: { children: ReactNode; className?: string }) {
  return <p role="alert" className={cn('text-xs text-tone-open', className)}>{children}</p>
}

/** A quiet confirmation for a form (e.g. "Check your email"): plain text, no box. */
export function FormNote({ children, className }: { children: ReactNode; className?: string }) {
  return <p role="status" className={cn('text-sm leading-relaxed text-ink-secondary', className)}>{children}</p>
}
