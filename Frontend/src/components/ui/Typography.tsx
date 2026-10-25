import type { ElementType, HTMLAttributes, ReactNode } from 'react'
import { cn } from '@/lib/cn'

type HeadingVariant = 'display' | 'section' | 'card' | 'script'
const headingStyles: Record<HeadingVariant, string> = {
  display: 'text-4xl font-extrabold tracking-tight text-ink-primary sm:text-5xl lg:text-6xl text-balance',
  section: 'text-3xl font-extrabold tracking-tight text-ink-primary sm:text-4xl text-balance',
  card: 'text-base font-semibold text-ink-primary',
  script: 'font-script text-2xl font-normal text-netslip-deep',
}

export function Heading({ level = 2, variant = 'section', className, children, ...rest }: { level?: 1 | 2 | 3 | 4 | 5 | 6; variant?: HeadingVariant; children: ReactNode } & HTMLAttributes<HTMLHeadingElement>) {
  const Tag = `h${level}` as ElementType
  return <Tag className={cn(headingStyles[variant], className)} {...rest}>{children}</Tag>
}

type TextVariant = 'body' | 'lead' | 'small' | 'caption' | 'mono' | 'eyebrow'
type TextTone = 'primary' | 'secondary' | 'muted' | 'brand' | 'danger' | 'inverse'
const textVariants: Record<TextVariant, string> = {
  body: 'text-sm leading-relaxed',
  lead: 'text-lg leading-relaxed sm:text-xl text-pretty',
  small: 'text-sm',
  caption: 'text-xs',
  mono: 'font-mono text-xs',
  eyebrow: 'text-xs font-semibold uppercase tracking-[0.14em]',
}
const tones: Record<TextTone, string> = {
  primary: 'text-ink-primary',
  secondary: 'text-ink-secondary',
  muted: 'text-ink-muted',
  brand: 'text-netslip-primary',
  danger: 'text-rose-600',
  inverse: 'text-[#cfe3e8]',
}

export function Text({ as: Tag = 'p', variant = 'body', tone = 'secondary', className, children, ...rest }: { as?: ElementType; variant?: TextVariant; tone?: TextTone; children: ReactNode } & HTMLAttributes<HTMLElement>) {
  return <Tag className={cn(textVariants[variant], tones[tone], className)} {...rest}>{children}</Tag>
}
