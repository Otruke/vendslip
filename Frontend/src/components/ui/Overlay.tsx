import { X } from 'lucide-react'
import { useEffect, useId, useRef, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { cn } from '@/lib/cn'
import { IconButton } from './Button'

/** Stack of open overlays so Escape/Tab only affect the top-most one (modal over drawer). */
const stack: symbol[] = []

function useOverlay(isOpen: boolean, onClose: () => void) {
  const panelRef = useRef<HTMLDivElement>(null)
  useEffect(() => {
    if (!isOpen) return
    const token = Symbol('overlay')
    stack.push(token)
    const isTop = () => stack[stack.length - 1] === token
    const previous = document.activeElement as HTMLElement | null
    const overflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    const onKey = (e: KeyboardEvent) => {
      if (!isTop()) return
      if (e.key === 'Escape') onClose()
      if (e.key === 'Tab' && panelRef.current) {
        const focusable = panelRef.current.querySelectorAll<HTMLElement>('a[href],button:not([disabled]),input:not([disabled]),select,textarea,[tabindex]:not([tabindex="-1"])')
        const first = focusable[0]
        const last = focusable[focusable.length - 1]
        if (!first || !last) return
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus() }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus() }
      }
    }
    document.addEventListener('keydown', onKey)
    requestAnimationFrame(() => panelRef.current?.querySelector<HTMLElement>('[autofocus],input,select,textarea,button')?.focus())
    return () => {
      stack.splice(stack.indexOf(token), 1)
      document.removeEventListener('keydown', onKey)
      document.body.style.overflow = overflow
      previous?.focus()
    }
  }, [isOpen, onClose])
  return panelRef
}

const modalSizes = { sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' }

export function Modal({ isOpen, onClose, title, description, size = 'md', children, footer }: { isOpen: boolean; onClose: () => void; title: ReactNode; description?: ReactNode; size?: keyof typeof modalSizes; children: ReactNode; footer?: ReactNode }) {
  const titleId = useId()
  const panelRef = useOverlay(isOpen, onClose)
  if (!isOpen) return null
  return createPortal(
    <div className="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4" role="presentation">
      <div className="absolute inset-0 bg-slate-900/40 backdrop-blur-sm animate-fade-up" onClick={onClose} aria-hidden />
      <div ref={panelRef} role="dialog" aria-modal="true" aria-labelledby={titleId} className={cn('relative flex max-h-[92dvh] w-full flex-col rounded-t-2xl bg-white shadow-2xl animate-slip-in sm:rounded-2xl', modalSizes[size])}>
        <div className="flex items-start justify-between gap-4 border-b border-border-subtle px-5 py-4">
          <div>
            <h2 id={titleId} className="text-base font-semibold text-ink-primary">{title}</h2>
            {description && <p className="mt-0.5 text-sm text-ink-secondary">{description}</p>}
          </div>
          <IconButton icon={<X className="size-4" />} label="Close" onClick={onClose} />
        </div>
        <div className="overflow-y-auto px-5 py-5">{children}</div>
        {footer && <div className="flex flex-wrap justify-end gap-2 border-t border-border-subtle px-5 py-4">{footer}</div>}
      </div>
    </div>,
    document.body,
  )
}

export function Drawer({ isOpen, onClose, title, subtitle, children, footer, width = 'max-w-xl' }: { isOpen: boolean; onClose: () => void; title: ReactNode; subtitle?: ReactNode; children: ReactNode; footer?: ReactNode; width?: string }) {
  const titleId = useId()
  const panelRef = useOverlay(isOpen, onClose)
  if (!isOpen) return null
  return createPortal(
    <div className="fixed inset-0 z-50 flex justify-end" role="presentation">
      <div className="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]" onClick={onClose} aria-hidden />
      <div ref={panelRef} role="dialog" aria-modal="true" aria-labelledby={titleId} className={cn('relative flex h-dvh w-full flex-col bg-white shadow-2xl animate-[slip-in_300ms_ease-out]', width)}>
        <div className="flex items-start justify-between gap-4 border-b border-border-subtle px-5 py-4">
          <div className="min-w-0">
            <h2 id={titleId} className="truncate text-base font-semibold text-ink-primary">{title}</h2>
            {subtitle && <div className="mt-0.5 text-sm text-ink-secondary">{subtitle}</div>}
          </div>
          <IconButton icon={<X className="size-4" />} label="Close" onClick={onClose} />
        </div>
        <div className="flex-1 overflow-y-auto px-5 py-5">{children}</div>
        {footer && <div className="flex flex-wrap justify-end gap-2 border-t border-border-subtle px-5 py-4">{footer}</div>}
      </div>
    </div>,
    document.body,
  )
}
