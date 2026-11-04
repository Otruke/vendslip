import { useEffect, useRef } from 'react'
import { heroBackground } from '@/assets'
import { cn } from '@/lib/cn'

type Slip = { x: number; y: number; vx: number; vy: number; rot: number; vr: number; size: number; depth: number }

const TEAL = '55, 117, 129'

/** One small slip in the VendSlip logo's language: scalloped tear edge on top, folded corner. */
function drawSlip(ctx: CanvasRenderingContext2D, s: Slip, lit: number, fade: number) {
  const w = s.size
  const h = s.size * 1.25
  const scallops = 5
  const r = w / scallops / 2
  const fold = w * 0.32
  ctx.save()
  ctx.translate(s.x, s.y)
  ctx.rotate(s.rot)
  ctx.globalAlpha = fade
  ctx.beginPath()
  ctx.moveTo(-w / 2, -h / 2 + r)
  for (let i = 0; i < scallops; i++) {
    ctx.arc(-w / 2 + r + i * 2 * r, -h / 2 + r, r, Math.PI, 0)
  }
  ctx.lineTo(w / 2, h / 2)
  ctx.lineTo(-w / 2 + fold, h / 2)
  ctx.lineTo(-w / 2, h / 2 - fold)
  ctx.closePath()
  ctx.fillStyle = `rgba(255, 255, 255, ${0.6 + 0.35 * s.depth})`
  ctx.fill()
  ctx.lineWidth = 1
  ctx.strokeStyle = `rgba(${TEAL}, ${0.25 + 0.25 * s.depth + 0.45 * lit})`
  ctx.stroke()
  // Folded corner and two printed lines
  ctx.fillStyle = `rgba(${TEAL}, ${0.18 + 0.35 * lit})`
  ctx.beginPath()
  ctx.moveTo(-w / 2, h / 2 - fold)
  ctx.lineTo(-w / 2 + fold, h / 2 - fold)
  ctx.lineTo(-w / 2 + fold, h / 2)
  ctx.closePath()
  ctx.fill()
  ctx.fillRect(-w / 2 + 4, -h / 2 + 2 * r + 4, w * 0.62, 1.5)
  ctx.fillRect(-w / 2 + 4, -h / 2 + 2 * r + 9, w * 0.38, 1.5)
  ctx.restore()
}

/**
 * Hero background: VendSlip's own flat illustration (ledger paper with a giant receipt in the logo's
 * shape, art-directed for landscape and portrait) plus an interactive layer of drifting slips that
 * part around the cursor and link to it with fine teal traces. No gradients anywhere. Static when
 * reduced motion is on.
 */
export function HeroBackdrop({ className }: { className?: string }) {
  const canvasRef = useRef<HTMLCanvasElement>(null)

  useEffect(() => {
    const canvas = canvasRef.current
    const ctx = canvas?.getContext('2d')
    if (!canvas || !ctx) return
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    const finePointer = window.matchMedia('(pointer: fine)').matches

    let width = 0
    let height = 0
    let slips: Slip[] = []
    const mouse = { x: -9999, y: -9999, active: false }
    let frame = 0
    let visible = true

    const seed = () => {
      const count = Math.max(10, Math.min(34, Math.round((width * height) / 42000)))
      slips = Array.from({ length: count }, () => {
        const depth = 0.35 + Math.random() * 0.65
        return {
          x: Math.random() * width,
          y: Math.random() * height,
          vx: (Math.random() - 0.5) * 0.12,
          vy: -(0.12 + Math.random() * 0.22) * depth,
          rot: (Math.random() - 0.5) * 0.9,
          vr: (Math.random() - 0.5) * 0.002,
          size: 12 + depth * 18,
          depth,
        }
      })
    }

    const resize = () => {
      const rect = canvas.getBoundingClientRect()
      const dpr = Math.min(window.devicePixelRatio || 1, 2)
      width = rect.width
      height = rect.height
      canvas.width = Math.round(width * dpr)
      canvas.height = Math.round(height * dpr)
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
      if (slips.length === 0) seed()
      if (reduced) draw()
    }

    // On wide screens the headline sits on the left: slips fade out as they drift towards it.
    const clearOfCopy = (x: number) => {
      if (width < 1024) return 0.55
      const t = (x / width - 0.36) / 0.16
      return Math.max(0, Math.min(1, t))
    }

    const draw = () => {
      ctx.clearRect(0, 0, width, height)
      for (const s of slips) {
        const dx = s.x - mouse.x
        const dy = s.y - mouse.y
        const dist = Math.hypot(dx, dy)
        const lit = mouse.active ? Math.max(0, 1 - dist / 170) : 0
        if (lit > 0) {
          // A right-angled trace, like the circuit lines in the wallpaper.
          ctx.strokeStyle = `rgba(${TEAL}, ${0.35 * lit})`
          ctx.lineWidth = 1
          ctx.beginPath()
          ctx.moveTo(mouse.x, mouse.y)
          ctx.lineTo(s.x, mouse.y)
          ctx.lineTo(s.x, s.y)
          ctx.stroke()
          ctx.fillStyle = `rgba(${TEAL}, ${0.6 * lit})`
          ctx.fillRect(s.x - 1.5, mouse.y - 1.5, 3, 3)
        }
        drawSlip(ctx, s, lit, clearOfCopy(s.x))
      }
    }

    const step = () => {
      for (const s of slips) {
        if (mouse.active) {
          const dx = s.x - mouse.x
          const dy = s.y - mouse.y
          const dist = Math.hypot(dx, dy) || 1
          if (dist < 130) {
            const push = (1 - dist / 130) * 0.6
            s.vx += (dx / dist) * push * 0.08
            s.vy += (dy / dist) * push * 0.08
          }
        }
        s.vx *= 0.985
        s.vy = s.vy * 0.985 + -(0.12 + 0.2 * s.depth) * s.depth * 0.015
        s.x += s.vx + Math.sin((s.y + s.size * 40) / 120) * 0.08
        s.y += s.vy
        s.rot += s.vr
        if (s.y < -40) { s.y = height + 40; s.x = Math.random() * width }
        if (s.x < -40) s.x = width + 40
        if (s.x > width + 40) s.x = -40
      }
      draw()
    }

    const loop = () => {
      if (visible && !document.hidden) step()
      frame = requestAnimationFrame(loop)
    }

    const onMove = (e: PointerEvent) => {
      const rect = canvas.getBoundingClientRect()
      mouse.x = e.clientX - rect.left
      mouse.y = e.clientY - rect.top
      mouse.active = mouse.y >= 0 && mouse.y <= rect.height
    }
    const onLeave = () => { mouse.active = false }

    const ro = new ResizeObserver(resize)
    ro.observe(canvas)
    resize()
    const io = new IntersectionObserver(([entry]) => { visible = entry?.isIntersecting ?? true })
    io.observe(canvas)

    if (!reduced) {
      frame = requestAnimationFrame(loop)
      if (finePointer) {
        window.addEventListener('pointermove', onMove, { passive: true })
        document.documentElement.addEventListener('pointerleave', onLeave)
      }
    }

    return () => {
      cancelAnimationFrame(frame)
      ro.disconnect()
      io.disconnect()
      window.removeEventListener('pointermove', onMove)
      document.documentElement.removeEventListener('pointerleave', onLeave)
    }
  }, [])

  return (
    <div className={cn('pointer-events-none absolute inset-0 -z-10 overflow-hidden bg-[#F2F6F7]', className)} aria-hidden>
      <picture>
        <source media="(max-width: 1023px)" srcSet={heroBackground.portrait} />
        <img src={heroBackground.landscape} alt="" className="absolute inset-0 h-full w-full object-cover object-right" fetchPriority="high" decoding="async" />
      </picture>
      <canvas ref={canvasRef} className="absolute inset-0 h-full w-full" />
    </div>
  )
}
