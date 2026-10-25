import { ChevronDown } from 'lucide-react'
import { useId, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react'
import { cn } from '@/lib/cn'

const fieldBase =
  'block w-full rounded-lg border bg-white text-sm text-ink-primary placeholder:text-ink-muted transition focus:outline-none focus:ring-2 focus:ring-netslip-primary/20 disabled:bg-surface-subtle disabled:text-ink-muted'
const stateClasses = (error?: string) => (error ? 'border-rose-400 focus:border-rose-500' : 'border-slate-300 focus:border-netslip-primary')

export function Field({ id, label, error, helperText, children, className, labelAction }: { id: string; label?: ReactNode; error?: string; helperText?: ReactNode; children: ReactNode; className?: string; labelAction?: ReactNode }) {
  return (
    <div className={className}>
      {label && (
        <div className="mb-1.5 flex items-center justify-between gap-2">
          <label htmlFor={id} className="block text-sm font-medium text-ink-primary">{label}</label>
          {labelAction}
        </div>
      )}
      {children}
      {error ? (
        <p id={`${id}-error`} role="alert" className="mt-1 text-xs text-rose-600">{error}</p>
      ) : helperText ? (
        <p className="mt-1 text-xs text-ink-muted">{helperText}</p>
      ) : null}
    </div>
  )
}

type InputProps = InputHTMLAttributes<HTMLInputElement> & {
  label?: ReactNode
  error?: string
  helperText?: ReactNode
  prefixIcon?: ReactNode
  suffix?: ReactNode
  wrapperClassName?: string
  labelAction?: ReactNode
  ref?: React.Ref<HTMLInputElement>
}

export function Input({ label, error, helperText, prefixIcon, suffix, className, wrapperClassName, id, labelAction, ref, ...rest }: InputProps) {
  const autoId = useId()
  const inputId = id ?? autoId
  return (
    <Field id={inputId} label={label} error={error} helperText={helperText} className={wrapperClassName} labelAction={labelAction}>
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

type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & { label?: ReactNode; error?: string; helperText?: ReactNode; wrapperClassName?: string; ref?: React.Ref<HTMLTextAreaElement> }

export function Textarea({ label, error, helperText, className, wrapperClassName, id, ref, ...rest }: TextareaProps) {
  const autoId = useId()
  const inputId = id ?? autoId
  return (
    <Field id={inputId} label={label} error={error} helperText={helperText} className={wrapperClassName}>
      <textarea ref={ref} id={inputId} aria-invalid={error ? true : undefined} className={cn(fieldBase, stateClasses(error), 'px-3 py-2', className)} {...rest} />
    </Field>
  )
}

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
  label?: ReactNode
  error?: string
  helperText?: ReactNode
  options: { value: string; label: string; disabled?: boolean }[]
  placeholder?: string
  wrapperClassName?: string
  ref?: React.Ref<HTMLSelectElement>
}

export function Select({ label, error, helperText, options, placeholder, className, wrapperClassName, id, ref, ...rest }: SelectProps) {
  const autoId = useId()
  const inputId = id ?? autoId
  return (
    <Field id={inputId} label={label} error={error} helperText={helperText} className={wrapperClassName}>
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

export function ToggleSwitch({ checked, onChange, label, description, disabled, id }: { checked: boolean; onChange: (value: boolean) => void; label: ReactNode; description?: ReactNode; disabled?: boolean; id?: string }) {
  const autoId = useId()
  const switchId = id ?? autoId
  return (
    <div className="flex items-start justify-between gap-4">
      <label htmlFor={switchId} className="min-w-0 cursor-pointer">
        <span className="block text-sm font-medium text-ink-primary">{label}</span>
        {description && <span className="mt-0.5 block text-xs text-ink-secondary">{description}</span>}
      </label>
      <button
        id={switchId}
        type="button"
        role="switch"
        aria-checked={checked}
        disabled={disabled}
        onClick={() => onChange(!checked)}
        className={cn('relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full transition disabled:opacity-50', checked ? 'bg-netslip-primary' : 'bg-slate-300')}
      >
        <span className={cn('inline-block size-5 rounded-full bg-white shadow transition', checked ? 'translate-x-5.5' : 'translate-x-0.5')} />
      </button>
    </div>
  )
}

export function Checkbox({ label, className, ...rest }: InputHTMLAttributes<HTMLInputElement> & { label: ReactNode }) {
  return (
    <label className={cn('flex cursor-pointer items-center gap-2 text-sm text-ink-primary', className)}>
      <input type="checkbox" className="size-4 rounded border-slate-300 accent-netslip-primary" {...rest} />
      {label}
    </label>
  )
}
