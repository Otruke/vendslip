import { useEffect, useRef } from 'react'
import { heroBackground } from '@/assets'
import { cn } from '@/lib/cn'

type Slip = { x: number; y: number; vx: number; vy: number; rot: number; vr: number; size: number; depth: number }

const TEAL = '55, 117, 129'

/** One small paper slip: rounded top, serrated bottom, two printed lines. */
function drawSlip(ctx: CanvasRenderingContext2D, s: Slip, lit: number) {
  const w = s.size
  const h = s.size * 1.3
  const teeth = 5
  ctx.save()
  ctx.translate(s.x, s.y)
  ctx.rotate(s.rot)
  ctx.beginPath()
  ctx.moveTo(-w / 2, h / 2)
  ctx.lineTo(-w / 2, -h / 2 + 3)
  ctx.quadraticCurveTo(-w / 2, -h / 2, -w / 2 + 3, -h / 2)
  ctx.lineTo(w / 2 - 3, -h / 2)
  ctx.quadraticCurveTo(w / 2, -h / 2, w / 2, -h / 2 + 3)
  ctx.lineTo(w / 2, h / 2)
  for (let i = teeth; i > 0; i--) {
    const x0 = -w / 2 + (w / teeth) * i
    ctx.lineTo(x0 - w / teeth / 2, h / 2 - 3)
    ctx.lineTo(x0 - w / teeth, h / 2)
  }
  ctx.closePath()
  ctx.fillStyle = `rgba(255, 255, 255, ${0.55 + 0.4 * s.depth})`
  ctx.fill()
  ctx.lineWidth = 1
  ctx.strokeStyle = `rgba(${TEAL}, ${0.22 + 0.25 * s.depth + 0.45 * lit})`
  ctx.stroke()
  ctx.strokeStyle = `rgba(${TEAL}, ${0.18 + 0.3 * lit})`
  ctx.beginPath()
  ctx.moveTo(-w / 2 + 4, -h / 2 + 6)
  ctx.lineTo(w / 2 - 6, -h / 2 + 6)
  ctx.moveTo(-w / 2 + 4, -h / 2 + 10)
  ctx.lineTo(w / 4, -h / 2 + 10)
  ctx.stroke()
  ctx.restore()
}

/**
 * Hero background: the NetSlip geometric wallpaper (landscape / portrait art direction) plus an
 * interactive layer of drifting paper slips. Slips part around the cursor and link to it with
 * fine teal traces, echoing the circuit lines in the wallpaper. Static when reduced motion is on.
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

    const draw = () => {
      ctx.clearRect(0, 0, width, height)
      if (mouse.active) {
        const glow = ctx.createRadialGradient(mouse.x, mouse.y, 0, mouse.x, mouse.y, 220)
        glow.addColorStop(0, `rgba(${TEAL}, 0.10)`)
        glow.addColorStop(1, `rgba(${TEAL}, 0)`)
        ctx.fillStyle = glow
        ctx.fillRect(mouse.x - 220, mouse.y - 220, 440, 440)
      }
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
        drawSlip(ctx, s, lit)
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
    <div className={cn('pointer-events-none absolute inset-0 -z-10 overflow-hidden', className)} aria-hidden>
      <picture>
        <source media="(max-width: 767px) and (orientation: portrait)" srcSet={heroBackground.portrait.srcSet} sizes="100vw" />
        <img src={heroBackground.landscape.src} srcSet={heroBackground.landscape.srcSet} sizes="100vw" alt="" className="absolute inset-0 h-full w-full object-cover" fetchPriority="high" decoding="async" />
      </picture>
      {/* Reading wash: strong behind the copy, clear behind the demo. */}
      <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(248,250,252,0.92)_0%,rgba(248,250,252,0.7)_45%,rgba(248,250,252,0.85)_100%)] lg:bg-[linear-gradient(90deg,rgba(248,250,252,0.95)_0%,rgba(248,250,252,0.82)_38%,rgba(248,250,252,0.35)_70%,rgba(248,250,252,0.15)_100%)]" />
      {/* Slips stay clear of the headline: faded under the copy, full strength around the demo. */}
      <canvas ref={canvasRef} className="absolute inset-0 h-full w-full [mask-image:linear-gradient(180deg,transparent_0%,rgba(0,0,0,0.35)_30%,#000_60%)] lg:[mask-image:linear-gradient(90deg,transparent_0%,transparent_30%,rgba(0,0,0,0.5)_44%,#000_58%)]" />
      <div className="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-b from-transparent to-white" />
    </div>
  )
}
