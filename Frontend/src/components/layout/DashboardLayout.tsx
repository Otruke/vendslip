import { useQueryClient } from '@tanstack/react-query'
import {
  Bot, Coins, CreditCard, FileText, History, KeyRound, LayoutDashboard, LayoutTemplate, LifeBuoy, LogOut, Menu, Package, Plus, Settings, ShieldAlert, ShieldCheck, Sparkles, UserRound, Users, UsersRound, X,
} from 'lucide-react'
import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { Avatar, Button, StatusBadge } from '@/components/ui'
import { apiPost } from '@/hooks/queries'
import { resetCsrf } from '@/lib/api'
import { cn } from '@/lib/cn'
import { useAuthStore, useCan } from '@/stores/auth'
import { NotificationBell } from './NotificationBell'
import { useUiStore } from '@/stores/ui'

type NavItem = { to: string; label: string; icon: ReactNode; end?: boolean; manageOnly?: boolean }

const NAV: NavItem[] = [
  { to: '/dashboard', label: 'Overview', icon: <LayoutDashboard />, end: true },
  { to: '/dashboard/composer', label: '/Slash Composer', icon: <Sparkles /> },
  { to: '/dashboard/documents', label: 'Receipts & Invoices', icon: <FileText /> },
  { to: '/dashboard/templates', label: 'Templates', icon: <LayoutTemplate /> },
  { to: '/dashboard/customers', label: 'Customers & Debtors', icon: <UsersRound /> },
  { to: '/dashboard/inventory', label: 'Inventory', icon: <Package /> },
  { to: '/dashboard/team', label: 'Team', icon: <Users /> },
  { to: '/dashboard/channels', label: 'Bot Channels', icon: <Bot /> },
  { to: '/dashboard/billing', label: 'Billing & Credits', icon: <CreditCard /> },
  { to: '/dashboard/settings', label: 'Settings', icon: <Settings /> },
  { to: '/dashboard/activity', label: 'Activity log', icon: <History />, manageOnly: true },
  { to: '/dashboard/support', label: 'Help & support', icon: <LifeBuoy /> },
]

function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
  const session = useAuthStore((s) => s.session)
  const merchant = session?.merchant
  const usage = session?.usage
  const unlimited = usage?.monthly_quota === null
  const canManage = useCan('settings.manage')

  return (
    <div className="flex h-full flex-col bg-vendslip-deep text-white">
      <div className="px-5 pb-4 pt-5">
        <div className="flex items-center gap-2.5">
          <img src="/assets/brand/vendslip-icon-white.png" alt="" className="h-7 w-auto" />
          <span className="text-lg font-extrabold tracking-tight">VendSlip</span>
        </div>
        <div className="mt-5 rounded-xl bg-white/[0.07] px-3 py-2.5">
          <p className="truncate text-sm font-semibold">{merchant?.name}</p>
          <p className="text-xs text-[#cfe3e8]">{merchant?.effective_tier === 'BUSINESS_PLUS' ? 'Business Plus' : merchant?.effective_tier === 'PRO' ? 'Pro Merchant' : 'Free plan'}</p>
        </div>
      </div>
      <nav className="no-scrollbar flex-1 space-y-0.5 overflow-y-auto pl-3" aria-label="Dashboard">
        {NAV.filter((item) => !item.manageOnly || canManage).map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.end}
            onClick={onNavigate}
            className={({ isActive }) => cn(
              'vs-nav flex items-center gap-3 rounded-l-lg px-3 py-2 text-sm font-medium transition-colors duration-300 [&>svg]:size-4 [&>svg]:shrink-0',
              isActive ? 'text-white' : 'text-[#cfe3e8] hover:text-white',
            )}
          >
            {item.icon}{item.label}
          </NavLink>
        ))}
      </nav>
      <div className="space-y-3 p-3">
        <div className="rounded-xl bg-white/[0.07] p-3">
          <div className="flex items-center justify-between text-xs">
            <span className="font-semibold uppercase tracking-wider text-[#cfe3e8]">{unlimited ? 'Unlimited plan' : 'Free slips'}</span>
            <span className="size-2 rounded-full bg-tone-settled-light" aria-hidden />
          </div>
          {!unlimited && usage && (
            <>
              <p className="tabular mt-1.5 text-sm font-semibold">{usage.free_used} / {usage.monthly_quota} used</p>
              <div className="mt-2 h-1.5 rounded-full bg-white/10"><div className="h-1.5 rounded-full bg-tone-settled-light" style={{ width: `${Math.min(100, (usage.free_used / Math.max(1, usage.monthly_quota ?? 1)) * 100)}%` }} /></div>
            </>
          )}
          <p className="tabular mt-2 flex items-center gap-1.5 text-xs text-[#cfe3e8]"><Coins className="size-3.5" />{usage?.paid_credits ?? 0} non-expiring credits</p>
          <Button to="/dashboard/billing" size="sm" variant="white" fullWidth className="mt-3" leftIcon={<Plus className="size-4" />} onClick={onNavigate}>Top up credits</Button>
        </div>
      </div>
    </div>
  )
}

function UserMenu() {
  const session = useAuthStore((s) => s.session)
  const setSession = useAuthStore((s) => s.setSession)
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  if (!session) return null

  const logout = async () => {
    const supportReturn = session.support?.return_url
    try { await apiPost('/auth/logout') } finally {
      resetCsrf()
      queryClient.clear()
      setSession(null)
      if (supportReturn) window.location.assign(supportReturn)
      else navigate('/login', { replace: true })
    }
  }

  return (
    <details className="relative">
      <summary className="flex cursor-pointer list-none items-center gap-2 rounded-lg p-1 hover:bg-surface-subtle [&::-webkit-details-marker]:hidden">
        <Avatar name={session.user.name} src={session.user.avatar_url} />
        <span className="hidden text-left text-sm leading-tight sm:block">
          <span className="block font-semibold text-ink-primary">{session.user.name}</span>
          <span className="block text-xs text-ink-muted">{session.user.role_label}</span>
        </span>
      </summary>
      <div className="absolute right-0 z-30 mt-2 w-56 rounded-xl border border-border-subtle bg-white p-1.5 shadow-lg">
        <p className="truncate px-3 py-2 text-xs text-ink-muted">{session.user.email}</p>
        <NavLink to="/dashboard/profile" className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-subtle"><UserRound className="size-4" />My profile</NavLink>
        <NavLink to="/dashboard/profile#password" className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-subtle"><KeyRound className="size-4" />Change password</NavLink>
        <NavLink to="/dashboard/settings" className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-subtle"><Settings className="size-4" />Business settings</NavLink>
        <button type="button" onClick={logout} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-tone-open hover:bg-tone-open/10"><LogOut className="size-4" />Sign out</button>
      </div>
    </details>
  )
}

/**
 * Phones and tablets: a tab bar along the bottom (thumb reach) with the everyday destinations,
 * a raised "New slip" in the middle, and Menu for everything else. The active tab's top edge
 * gets the same water-flow line as the sidebar.
 */
function BottomNav({ onMenu, menuOpen }: { onMenu: () => void; menuOpen: boolean }) {
  const tab = 'vs-tab flex flex-col items-center justify-center gap-1 pt-2 pb-1.5 text-[10.5px] font-semibold transition-colors [&>svg]:size-5'
  const tone = ({ isActive }: { isActive: boolean }) => cn(tab, isActive ? 'text-vendslip-deep' : 'text-ink-muted hover:text-ink-primary')
  return (
    <nav className="fixed inset-x-0 bottom-0 z-40 border-t border-border-subtle bg-white pb-[env(safe-area-inset-bottom)] lg:hidden" aria-label="Quick navigation">
      <div className="mx-auto grid h-16 max-w-xl grid-cols-5">
        <NavLink to="/dashboard" end className={tone}><LayoutDashboard />Home</NavLink>
        <NavLink to="/dashboard/documents" className={tone}><FileText />Slips</NavLink>
        <NavLink to="/dashboard/composer" className={({ isActive }) => cn('group flex flex-col items-center justify-end gap-1 pb-1.5 text-[10.5px] font-semibold', isActive ? 'text-vendslip-deep' : 'text-ink-secondary')} aria-label="New slip">
          <span className="-mt-6 flex size-13 items-center justify-center rounded-2xl bg-vendslip-deep text-white ring-4 ring-white transition-transform duration-300 group-hover:-translate-y-0.5 group-active:scale-95 [&>svg]:size-6"><Plus /></span>
          New slip
        </NavLink>
        <NavLink to="/dashboard/profile" className={tone}><UserRound />Profile</NavLink>
        <button type="button" onClick={onMenu} aria-expanded={menuOpen} aria-label="Open the full menu" className={cn(tab, menuOpen ? 'text-vendslip-deep' : 'text-ink-muted hover:text-ink-primary')}><Menu />Menu</button>
      </div>
    </nav>
  )
}

/**
 * Shown for the whole of an owner-approved support session so nobody mistakes support for the owner.
 * Ends the session (and returns to the admin portal) on demand or when the time runs out.
 */
function SupportBanner() {
  const support = useAuthStore((s) => s.session?.support)
  const merchantName = useAuthStore((s) => s.session?.merchant?.name)
  const setSession = useAuthStore((s) => s.setSession)
  const queryClient = useQueryClient()
  const [busy, setBusy] = useState(false)

  const end = useCallback(async () => {
    if (!support) return
    setBusy(true)
    try { await apiPost('/auth/logout') } catch { /* the session may already be gone */ } finally {
      resetCsrf()
      queryClient.clear()
      setSession(null)
      window.location.assign(support.return_url)
    }
  }, [queryClient, setSession, support])

  useEffect(() => {
    if (!support) return
    const ms = new Date(support.expires_at).getTime() - Date.now()
    const timer = window.setTimeout(() => { void end() }, Math.max(ms, 0))
    return () => window.clearTimeout(timer)
  }, [end, support])

  if (!support) return null
  const ends = new Date(support.expires_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })

  return (
    <div role="status" className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 bg-vendslip-obsidian px-4 py-2 text-sm text-white sm:px-6 lg:px-8">
      <p className="flex min-w-0 flex-1 items-start gap-2">
        <LifeBuoy className="mt-0.5 size-4 shrink-0 text-[#9cc3cc]" aria-hidden />
        <span className="min-w-0"><strong className="font-semibold">Support session</strong><span className="text-[#cfe3e8]"> · {support.admin_name}, VendSlip · owner-approved{merchantName ? ` for ${merchantName}` : ''} · ends {ends}</span></span>
      </p>
      <button type="button" disabled={busy} onClick={() => void end()} className="shrink-0 rounded-md bg-white px-3 py-1 text-xs font-semibold text-vendslip-deep hover:bg-[#e3eef1] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white disabled:opacity-60">
        {busy ? 'Ending…' : 'End support session'}
      </button>
    </div>
  )
}

/**
 * A small header pill (not a banner) until the signed-in person turns on two-factor sign-in. It
 * moves just enough to be noticed; tapping it opens a short explanation. "Later" hides it for the
 * current visit only. Never shown in support sessions or on the profile page.
 */
function TwoFactorNudge() {
  const session = useAuthStore((s) => s.session)
  const { pathname } = useLocation()
  const [hidden, setHidden] = useState(false)
  const [open, setOpen] = useState(false)
  const box = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return
    const away = (e: PointerEvent) => { if (!box.current?.contains(e.target as Node)) setOpen(false) }
    const esc = (e: KeyboardEvent) => { if (e.key === 'Escape') setOpen(false) }
    document.addEventListener('pointerdown', away)
    document.addEventListener('keydown', esc)
    return () => { document.removeEventListener('pointerdown', away); document.removeEventListener('keydown', esc) }
  }, [open])

  if (!session || session.user.two_factor_enabled || session.support || hidden || pathname.startsWith('/dashboard/profile')) return null

  return (
    <div ref={box} className="relative">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        aria-expanded={open}
        aria-haspopup="dialog"
        aria-label="Your account isn’t fully protected. Turn on two-factor sign-in"
        className="vs-nudge group inline-flex h-8 items-center gap-1.5 rounded-full px-2 text-xs font-semibold text-tone-partial transition-colors sm:px-2.5 cursor-pointer"
      >
        <span className="relative inline-flex items-center">
          <span className="vs-nudge__halo" aria-hidden />
          <ShieldAlert className="vs-nudge__icon size-4" aria-hidden />
        </span>
        
        {/* Removed pb-1 so flex centering stays aligned, using -bottom-1 for spacing */}
        <span className="hidden sm:inline relative">
          Secure your account
          <span className="absolute -bottom-1 left-1/2 h-[1.5px] w-0 -translate-x-1/2 bg-current transition-all duration-500 ease-out group-hover:w-full" />
        </span>
      </button>
      {open && (
        <div role="dialog" aria-label="Two-factor sign-in" className="absolute right-0 z-40 mt-2 w-[min(18rem,calc(100vw-2rem))] animate-fade-up rounded-xl border border-border-subtle bg-white p-4 shadow-lg">
          <p className="flex items-center gap-2 text-sm font-semibold text-ink-primary"><ShieldCheck className="size-4 text-vendslip-primary" aria-hidden />Turn on two-factor sign-in</p>
          <p className="mt-1.5 text-xs leading-relaxed text-ink-secondary">A 6-digit code from an authenticator app keeps your slips and payouts safe, even if your password leaks.</p>
          <div className="mt-3 flex items-center justify-end gap-2">
            <Button size="xs" variant="ghost" onClick={() => { setHidden(true); setOpen(false) }}>Later</Button>
            <Button size="xs" to="/dashboard/profile#two-factor" onClick={() => setOpen(false)}>Turn it on</Button>
          </div>
        </div>
      )}
    </div>
  )
}

export default function DashboardLayout() {
  const sidebarOpen = useUiStore((s) => s.sidebarOpen)
  const setSidebarOpen = useUiStore((s) => s.setSidebarOpen)
  const merchant = useAuthStore((s) => s.session?.merchant)
  const { pathname } = useLocation()

  useEffect(() => {
    setSidebarOpen(false)
  }, [pathname, setSidebarOpen])

  return (
    <div className="min-h-dvh bg-surface-canvas lg:grid lg:grid-cols-[260px_minmax(0,1fr)]">
      <aside className="sticky top-0 hidden h-dvh lg:block"><Sidebar /></aside>

      {sidebarOpen && (
        <div className="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Navigation">
          <div className="absolute inset-0 bg-slate-900/50" onClick={() => setSidebarOpen(false)} aria-hidden />
          <div className="relative h-full w-[280px] animate-[slip-in_250ms_ease-out]">
            <Sidebar onNavigate={() => setSidebarOpen(false)} />
            <button type="button" onClick={() => setSidebarOpen(false)} className="absolute right-3 top-4 rounded-lg p-1.5 text-white/80 hover:bg-white/10" aria-label="Close navigation"><X className="size-5" /></button>
          </div>
        </div>
      )}

      <div className="min-w-0">
        <header className="sticky top-0 z-30 border-b border-border-subtle bg-white/90 backdrop-blur">
          <SupportBanner />
          <div className="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
            <div className="flex items-center gap-3">
              <img src="/assets/brand/vendslip-icon.png" alt="VendSlip" className="h-7 w-auto lg:hidden" />
              {merchant && <StatusBadge status={merchant.record_mutability_mode} size="sm" showIcon className="hidden sm:inline-flex" />}
              {merchant?.status === 'SUSPENDED' && <StatusBadge status="SUSPENDED" size="sm" />}
              <TwoFactorNudge />
            </div>
            <div className="flex items-center gap-2 sm:gap-3">
              <Button to="/dashboard/composer" size="sm" leftIcon={<Plus className="size-4" />} className="hidden lg:inline-flex">New slip</Button>
              {merchant && <NotificationBell />}
              <UserMenu />
            </div>
          </div>
        </header>
        <main className="mx-auto w-full max-w-[1440px] px-4 pt-6 pb-28 sm:px-6 lg:px-8 lg:py-8">
          <Outlet />
        </main>
      </div>
      <BottomNav onMenu={() => setSidebarOpen(!sidebarOpen)} menuOpen={sidebarOpen} />
    </div>
  )
}
