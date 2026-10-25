import { useMemo } from 'react'

/**
 * Decorative QR-style stamp for previews. The real, scannable QR (HMAC verification URL)
 * is generated server-side when the slip is issued.
 */
export function QrStamp({ seed, size = 64, color = '#163540' }: { seed: string; size?: number; color?: string }) {
  const cells = useMemo(() => {
    let h = 2166136261
    for (const ch of seed) h = Math.imul(h ^ ch.charCodeAt(0), 16777619)
    const out: boolean[] = []
    for (let i = 0; i < 21 * 21; i++) {
      h ^= h << 13; h ^= h >>> 17; h ^= h << 5
      out.push((h & 3) === 0 || (h & 7) === 5)
    }
    return out
  }, [seed])

  const finder = (x: number, y: number) => (
    <g key={`f${x}${y}`}>
      <rect x={x} y={y} width={7} height={7} fill={color} />
      <rect x={x + 1} y={y + 1} width={5} height={5} fill="#fff" />
      <rect x={x + 2} y={y + 2} width={3} height={3} fill={color} />
    </g>
  )
  const inFinder = (x: number, y: number) => (x < 8 && y < 8) || (x > 12 && y < 8) || (x < 8 && y > 12)

  return (
    <svg width={size} height={size} viewBox="-1 -1 23 23" role="img" aria-label="Verification code stamp" shapeRendering="crispEdges">
      <rect x={-1} y={-1} width={23} height={23} fill="#fff" />
      {cells.map((on, i) => {
        const x = i % 21
        const y = Math.floor(i / 21)
        return on && !inFinder(x, y) ? <rect key={i} x={x} y={y} width={1} height={1} fill={color} /> : null
      })}
      {finder(0, 0)}{finder(14, 0)}{finder(0, 14)}
    </svg>
  )
}
