import { cn } from '@/lib/cn'

export type ButtonVariant = 'primary' | 'deep' | 'secondary' | 'outline' | 'ghost' | 'danger' | 'success' | 'white'
export type ButtonSize = 'xs' | 'sm' | 'md' | 'lg'

const variants: Record<ButtonVariant, string> = {
  primary: 'bg-vendslip-primary text-white shadow-sm shadow-vendslip-deep/10 hover:bg-vendslip-primary-hover',
  deep: 'bg-vendslip-deep text-white shadow-sm hover:bg-vendslip-obsidian',
  secondary: 'bg-vendslip-tint text-vendslip-deep hover:bg-[#dbe9ed]',
  outline: 'border border-slate-300 bg-white text-ink-primary hover:bg-surface-canvas',
  ghost: 'text-ink-secondary hover:bg-surface-subtle hover:text-ink-primary',
  danger: 'bg-tone-open text-white shadow-sm hover:bg-[#8a2a18]',
  success: 'bg-tone-settled text-white shadow-sm hover:bg-[#175843]',
  white: 'bg-white text-vendslip-deep shadow-sm hover:bg-vendslip-tint',
}

const sizes: Record<ButtonSize, string> = {
  xs: 'h-7 gap-1 rounded-md px-2.5 text-xs',
  sm: 'h-8 gap-1.5 rounded-lg px-3 text-sm',
  md: 'h-10 gap-2 rounded-lg px-4 text-sm',
  lg: 'h-12 gap-2 rounded-xl px-6 text-base',
}

export function buttonClasses({ variant = 'primary', size = 'md', fullWidth, className }: { variant?: ButtonVariant; size?: ButtonSize; fullWidth?: boolean; className?: string }) {
  return cn(
    'inline-flex select-none items-center justify-center whitespace-nowrap font-semibold transition-all duration-150 active:scale-[0.98] disabled:pointer-events-none disabled:opacity-50',
    variants[variant],
    sizes[size],
    fullWidth && 'w-full',
    className,
  )
}

