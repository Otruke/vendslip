import { useEffect, useId, useLayoutEffect, useRef, useState, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { cn } from '@/lib/cn'

/**
 * A small "i" that explains a setting without cluttering the page: hover (or focus) on desktop,
 * tap on phones and tablets. The bubble is placed in the viewport so it never runs off-screen.
 */
export function InfoTip({ children, label = 'More info', className }: { children: ReactNode; label?: string; className?: string }) {
  const id = useId()
  const button = useRef<HTMLButtonElement>(null)
  const bubble = useRef<HTMLDivElement>(null)
  const [open, setOpen] = useState(false)
  const [pinned, setPinned] = useState(false) // opened by a tap/click: stays until tapped away
  const [pos, setPos] = useState<{ top: number; left: number; above: boolean } | null>(null)

  useLayoutEffect(() => {
    if (!open || !button.current) return
    const place = () => {
      const r = button.current?.getBoundingClientRect()
      const b = bubble.current?.getBoundingClientRect()
      if (!r) return
      const width = b?.width ?? 260
      const height = b?.height ?? 60
      const left = Math.min(Math.max(12, r.left + r.width / 2 - width / 2), window.innerWidth - width - 12)
      const above = r.bottom + height + 12 > window.innerHeight && r.top > height + 12
      setPos({ top: above ? r.top - height - 8 : r.bottom + 8, left, above })
    }
    place()
    window.addEventListener('scroll', place, true)
    window.addEventListener('resize', place)
    return () => { window.removeEventListener('scroll', place, true); window.removeEventListener('resize', place) }
  }, [open])

  useEffect(() => {
    if (!pinned) return
    const away = (e: PointerEvent) => {
      if (!button.current?.contains(e.target as Node) && !bubble.current?.contains(e.target as Node)) { setPinned(false); setOpen(false) }
    }
    const esc = (e: KeyboardEvent) => { if (e.key === 'Escape') { setPinned(false); setOpen(false) } }
    document.addEventListener('pointerdown', away)
    document.addEventListener('keydown', esc)
    return () => { document.removeEventListener('pointerdown', away); document.removeEventListener('keydown', esc) }
  }, [pinned])

  return (
    <>
      <button
        ref={button}
        type="button"
        aria-label={label}
        aria-expanded={open}
        aria-describedby={open ? id : undefined}
        onClick={(e) => { e.preventDefault(); e.stopPropagation(); const next = !pinned; setPinned(next); setOpen(next) }}
        onMouseEnter={(e) => { if (e.nativeEvent instanceof MouseEvent && !pinned) setOpen(true) }}
        onMouseLeave={() => { if (!pinned) setOpen(false) }}
        onFocus={() => setOpen(true)}
        onBlur={() => { if (!pinned) setOpen(false) }}
        className={cn(
          'inline-flex size-4 shrink-0 items-center justify-center rounded-full border border-ink-muted/50 align-middle text-[10px] font-bold leading-none text-ink-muted transition-colors',
          'hover:border-vendslip-primary hover:text-vendslip-primary focus-visible:border-vendslip-primary focus-visible:text-vendslip-primary',
          open && 'border-vendslip-primary text-vendslip-primary',
          className,
        )}
      >
        <span aria-hidden className="font-serif italic">i</span>
      </button>
      {open && createPortal(
        <div
          ref={bubble}
          id={id}
          role="tooltip"
          style={{ top: pos?.top ?? -9999, left: pos?.left ?? -9999 }}
          className="pointer-events-auto fixed z-[80] w-max max-w-[min(280px,calc(100vw-24px))] rounded-lg bg-vendslip-obsidian px-3 py-2 text-xs font-normal leading-relaxed text-white shadow-lg"
        >
          {children}
        </div>,
        document.body,
      )}
    </>
  )
}
