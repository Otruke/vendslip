import type { ReactNode } from 'react'
import { cn } from '@/lib/cn'

/** Clock for the status bar: the visitor's own local time, not the usual marketing 9:41. */
function clock(): string {
  return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit', hour12: false }).format(new Date())
}

/**
 * A phone in a matte protective case: case rim with side-button bumps, black bezel, camera
 * island and status bar. Pure CSS so it stays sharp at every size.
 */
export function PhoneShell({ children, dark = false, tint = '#ffffff', className }: { children: ReactNode; dark?: boolean; tint?: string; className?: string }) {
  return (
    <div className={cn('relative mx-auto w-[min(300px,78vw)] select-none', className)}>
      {/* Side buttons sit on the case, like a real protective cover */}
      <span className="absolute top-[22%] -left-[3px] h-9 w-[5px] rounded-l-md bg-[#1f3a42]" aria-hidden />
      <span className="absolute top-[30%] -left-[3px] h-14 w-[5px] rounded-l-md bg-[#1f3a42]" aria-hidden />
      <span className="absolute top-[41%] -left-[3px] h-14 w-[5px] rounded-l-md bg-[#1f3a42]" aria-hidden />
      <span className="absolute top-[28%] -right-[3px] h-20 w-[5px] rounded-r-md bg-[#1f3a42]" aria-hidden />

      {/* Case */}
      <div className="rounded-[3.1rem] bg-[linear-gradient(145deg,#2f5560_0%,#24454f_45%,#1b363e_100%)] p-[9px] shadow-[0_40px_80px_-30px_rgba(10,30,36,0.65),0_18px_36px_-18px_rgba(10,30,36,0.45),inset_0_1px_0_rgba(255,255,255,0.12),inset_0_-2px_6px_rgba(0,0,0,0.35)]">
        {/* Phone body + bezel */}
        <div className="rounded-[2.6rem] bg-[#0b0f11] p-[7px] shadow-[inset_0_0_0_1px_rgba(255,255,255,0.06)]">
          <div className="relative overflow-hidden rounded-[2.15rem] bg-white">
            {/* Status bar */}
            <div className={cn('relative z-20 flex h-9 items-center justify-between px-6 pt-1 text-[11px] font-semibold transition-colors duration-500', dark ? 'text-white' : 'text-[#0b0f11]')} style={{ background: tint }}>
              <span className="tabular">{clock()}</span>
              <span className="flex items-center gap-1" aria-hidden>
                <svg viewBox="0 0 18 12" className="h-2.5 w-[15px]" fill="currentColor"><rect x="0" y="8" width="3" height="4" rx="0.8" /><rect x="5" y="5.5" width="3" height="6.5" rx="0.8" /><rect x="10" y="3" width="3" height="9" rx="0.8" /><rect x="15" y="0" width="3" height="12" rx="0.8" /></svg>
                <svg viewBox="0 0 16 12" className="h-2.5 w-[13px]" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round"><path d="M1.5 4.5a9.5 9.5 0 0 1 13 0" /><path d="M4 7.2a6 6 0 0 1 8 0" /><circle cx="8" cy="10" r="0.6" fill="currentColor" /></svg>
                <span className="relative ml-0.5 inline-flex h-[11px] w-[22px] items-center rounded-[3px] border border-current p-[1.5px] opacity-90"><span className="h-full w-[72%] rounded-[1.5px] bg-current" /><span className="absolute -right-[3px] top-1/2 h-1 w-[2px] -translate-y-1/2 rounded-r-sm bg-current" /></span>
              </span>
            </div>
            {/* Camera island */}
            <span className="absolute left-1/2 top-2 z-30 h-[22px] w-[86px] -translate-x-1/2 rounded-full bg-[#0b0f11]" aria-hidden>
              <span className="absolute right-3 top-1/2 size-[7px] -translate-y-1/2 rounded-full bg-[#1b2a33] shadow-[inset_0_0_2px_rgba(80,140,170,0.8)]" />
            </span>
            {children}
            {/* Home indicator */}
            <span className="absolute bottom-1.5 left-1/2 z-20 h-1 w-24 -translate-x-1/2 rounded-full bg-black/70" aria-hidden />
          </div>
        </div>
      </div>
    </div>
  )
}
