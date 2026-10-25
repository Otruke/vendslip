import { LoaderCircle } from 'lucide-react'
import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { cn } from '@/lib/cn'
import { buttonClasses } from './buttonStyles'

import type { ButtonSize, ButtonVariant } from './buttonStyles'

type Common = {
  variant?: ButtonVariant
  size?: ButtonSize
  isLoading?: boolean
  leftIcon?: ReactNode
  rightIcon?: ReactNode
  fullWidth?: boolean
  className?: string
  children?: ReactNode
}

export type ButtonProps = Common & ButtonHTMLAttributes<HTMLButtonElement> & { to?: undefined; href?: undefined }
type LinkButtonProps = Common & { to: string; href?: undefined; onClick?: () => void; target?: string }
type AnchorButtonProps = Common & { href: string; to?: undefined; target?: string; rel?: string; download?: string | boolean }

export function Button(props: ButtonProps | LinkButtonProps | AnchorButtonProps) {
  const { variant, size, isLoading, leftIcon, rightIcon, fullWidth, className, children } = props
  const classes = buttonClasses({ variant, size, fullWidth, className })
  const iconSize = size === 'lg' ? 'size-5' : 'size-4'
  const content = (
    <>
      {isLoading ? <LoaderCircle className={cn(iconSize, 'animate-spin')} aria-hidden /> : leftIcon}
      {children}
      {!isLoading && rightIcon}
    </>
  )

  if ('to' in props && props.to !== undefined) {
    return <Link to={props.to} className={classes} onClick={props.onClick} target={props.target}>{content}</Link>
  }
  if ('href' in props && props.href !== undefined) {
    const { href, target, rel, download } = props as AnchorButtonProps
    return <a href={href} target={target} rel={rel ?? (target === '_blank' ? 'noopener noreferrer' : undefined)} download={download} className={classes}>{content}</a>
  }

  const { variant: _v, size: _s, isLoading: _l, leftIcon: _li, rightIcon: _ri, fullWidth: _f, className: _c, children: _ch, disabled, type, ...rest } = props as ButtonProps
  void _v; void _s; void _l; void _li; void _ri; void _f; void _c; void _ch
  return (
    <button type={type ?? 'button'} disabled={disabled || isLoading} aria-busy={isLoading || undefined} className={classes} {...rest}>
      {content}
    </button>
  )
}

export function IconButton({ icon, label, className, ...rest }: { icon: ReactNode; label: string } & ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button type="button" aria-label={label} title={label} className={cn('inline-flex size-8 items-center justify-center rounded-lg text-ink-secondary transition hover:bg-surface-subtle hover:text-ink-primary disabled:opacity-40', className)} {...rest}>
      {icon}
    </button>
  )
}
