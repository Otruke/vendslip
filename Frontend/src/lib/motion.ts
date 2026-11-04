import { useEffect, type RefObject } from 'react'

/**
 * VendSlip scroll motion. One IntersectionObserver for the whole page, driven by data attributes,
 * so the pages stay plain JSX:
 *
 *   data-reveal="rise | feed | stamp | rule | tear | type"   how an element enters (see index.css);
 *                                                            "type" headings wrap their words in <Words>
 *   data-stagger(="40")                                     children with data-reveal enter in turn (step in ms)
 *   data-count                                               a number that ticks up like a till
 *   data-parallax="0.12"                                     a background photo that drifts on scroll
 *
 * Hidden states only apply inside an element with the `vs-motion` class (the public and auth
 * layouts), so shared components that carry data-reveal still render normally in the dashboard.
 * Elements play in when they scroll into view and back out when they leave, so scrolling back
 * up replays them. data-from records which edge an element left by ("above" or "below") so it
 * exits the way it went. Nothing is hidden until JS has switched motion on (see enableMotion),
 * and reduced-motion users get the finished page with no movement at all.
 */

const reducedMotion = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches

/** Called once before React renders. Without it, every data-reveal element is simply visible. */
export function enableMotion(): void {
  if (!reducedMotion()) document.documentElement.dataset.motion = 'on'
}

/** Writes into the element's own text node (never replaces it), so React's node stays intact. */
function setText(el: HTMLElement, value: string): void {
  const node = el.firstChild
  if (node && node.nodeType === Node.TEXT_NODE) node.nodeValue = value
}

function countUp(el: HTMLElement): void {
  const target = Number(el.dataset.countTo ?? '0')
  if (!Number.isFinite(target)) return
  const start = performance.now()
  const duration = 900 + Math.min(target, 40) * 12
  const step = (now: number) => {
    const t = Math.min(1, (now - start) / duration)
    // Ease out like a till drum slowing to a stop.
    const eased = 1 - (1 - t) ** 3
    setText(el, String(Math.round(target * eased)))
    if (t < 1 && el.dataset.inview !== undefined) requestAnimationFrame(step)
    else setText(el, String(target))
  }
  requestAnimationFrame(step)
}

/**
 * Binds every motion element under `root`. Re-scans when `key` changes (a new route) and when
 * content arrives later (pricing plans load after the first paint).
 */
export function useScrollMotion(root: RefObject<HTMLElement | null>, key: string): void {
  useEffect(() => {
    const host = root.current
    if (!host || document.documentElement.dataset.motion !== 'on') return undefined

    // Which elements each observed target drives. "feed" and "tear" are watched through their
    // parent: while hidden they are clipped to nothing, and Chromium counts a fully clipped
    // element as never intersecting, so they would otherwise never be revealed.
    const drives = new Map<Element, HTMLElement[]>()
    const io = new IntersectionObserver((entries) => {
      for (const entry of entries) {
        for (const el of drives.get(entry.target) ?? []) {
          if (entry.isIntersecting) {
            if (el.dataset.inview !== undefined) continue
            el.dataset.inview = ''
            if (el.dataset.count !== undefined) countUp(el)
          } else if (el.dataset.inview !== undefined) {
            delete el.dataset.inview
            el.dataset.from = entry.boundingClientRect.top < 0 ? 'above' : 'below'
          }
        }
      }
    }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' })
    const watch = (el: HTMLElement) => {
      // Anything inside a clipped reveal shares that reveal's trigger too (Chromium does not
      // reliably re-check targets while an ancestor's clip-path animates); its own --d/--i
      // delays then play it in sequence after the slip has fed out.
      let clipped: HTMLElement | null = null
      for (let node: HTMLElement | null = el; node && node !== host; node = node.parentElement) {
        if (node.dataset.reveal === 'feed' || node.dataset.reveal === 'tear') clipped = node
      }
      const target = clipped?.parentElement ?? el
      const list = drives.get(target)
      if (list) list.push(el)
      else {
        drives.set(target, [el])
        io.observe(target)
      }
    }

    const parallax = new Set<HTMLElement>()
    let frame = 0
    const drift = () => {
      frame = 0
      const vh = window.innerHeight
      for (const el of parallax) {
        const box = (el.parentElement ?? el).getBoundingClientRect()
        if (box.bottom < -200 || box.top > vh + 200) continue
        const factor = Number(el.dataset.parallax) || 0.1
        const offset = (box.top + box.height / 2 - vh / 2) * -factor
        el.style.transform = `translate3d(0, ${offset.toFixed(1)}px, 0)`
      }
    }
    const onScroll = () => {
      if (!frame) frame = requestAnimationFrame(drift)
    }

    const bind = () => {
      const fresh = host.querySelectorAll<HTMLElement>('[data-reveal]:not([data-bound]), [data-count]:not([data-bound])')
      if (fresh.length === 0) return
      host.querySelectorAll<HTMLElement>('[data-stagger]').forEach((group) => {
        // data-stagger="40" sets a tighter step (ms) for long lists such as table rows.
        const step = Number(group.dataset.stagger)
        let i = 0
        group.querySelectorAll<HTMLElement>(':scope > [data-reveal], :scope > * > [data-reveal]').forEach((child) => {
          if (child.closest('[data-stagger]') !== group) return
          child.style.setProperty('--i', String(i++))
          if (step > 0) child.style.setProperty('--step', `${step}ms`)
        })
      })
      fresh.forEach((el) => {
        el.dataset.bound = ''
        el.dataset.from ??= 'below'
        if (el.dataset.count !== undefined) {
          el.dataset.countTo = el.textContent ?? '0'
          setText(el, '0')
        }
        watch(el)
      })
      host.querySelectorAll<HTMLElement>('[data-parallax]').forEach((el) => parallax.add(el))
      onScroll()
    }

    // Cards (.vs-slip) lean a few degrees toward a mouse pointer; touch screens just lift.
    let tilted: HTMLElement | null = null
    const untilt = () => {
      if (!tilted) return
      tilted.style.removeProperty('--rx')
      tilted.style.removeProperty('--ry')
      tilted = null
    }
    const onPointer = (e: PointerEvent) => {
      if (e.pointerType !== 'mouse') return
      const card = (e.target as Element | null)?.closest<HTMLElement>('.vs-slip') ?? null
      if (card !== tilted) untilt()
      if (!card) return
      tilted = card
      const box = card.getBoundingClientRect()
      const x = (e.clientX - box.left) / box.width - 0.5
      const y = (e.clientY - box.top) / box.height - 0.5
      card.style.setProperty('--rx', `${(-y * 6).toFixed(2)}deg`)
      card.style.setProperty('--ry', `${(x * 7).toFixed(2)}deg`)
    }
    host.addEventListener('pointermove', onPointer)
    host.addEventListener('pointerleave', untilt)

    bind()
    // Late content (plans, verification results) is picked up in batches; the animated hero
    // demo mutates constantly, so re-scans are debounced rather than run per mutation.
    let pending = 0
    const mo = new MutationObserver(() => {
      window.clearTimeout(pending)
      host.removeEventListener('pointermove', onPointer)
      host.removeEventListener('pointerleave', untilt)
      untilt()
      pending = window.setTimeout(bind, 120)
    })
    mo.observe(host, { childList: true, subtree: true })
    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onScroll)

    return () => {
      io.disconnect()
      mo.disconnect()
      window.clearTimeout(pending)
      window.removeEventListener('scroll', onScroll)
      window.removeEventListener('resize', onScroll)
      if (frame) cancelAnimationFrame(frame)
      host.querySelectorAll<HTMLElement>('[data-bound]').forEach((el) => {
        delete el.dataset.bound
        if (el.dataset.countTo !== undefined) setText(el, el.dataset.countTo)
      })
    }
  }, [root, key])
}
