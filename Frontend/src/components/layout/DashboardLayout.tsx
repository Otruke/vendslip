import { useQueryClient } from '@tanstack/react-query'
import {
  Bot, Coins, CreditCard, FileText, LayoutDashboard, LayoutTemplate, LogOut, Menu, Package, Plus, Settings, Sparkles, Users, UsersRound, X,
} from 'lucide-react'
import { useEffect, type ReactNode } from 'react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { Avatar, Button, StatusBadge } from '@/components/ui'
import { apiPost } from '@/hooks/queries'
import { resetCsrf } from '@/lib/api'
import { cn } from '@/lib/cn'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'

type NavItem = { to: string; label: string; icon: ReactNode; end?: boolean }

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
]

function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
  const session = useAuthStore((s) => s.session)
  const merchant = session?.merchant
  const usage = session?.usage
  const unlimited = usage?.monthly_quota === null

  return (
    <div className="flex h-full flex-col bg-netslip-deep text-white">
      <div className="px-5 pb-4 pt-5">
        <div className="flex items-center gap-2.5">
          <img src="/assets/brand/netslip-icon-white.png" alt="" className="h-7 w-auto" />
          <span className="text-lg font-extrabold tracking-tight">NetSlip</span>
        </div>
        <div className="mt-5 rounded-xl bg-white/[0.07] px-3 py-2.5">
          <p className="truncate text-sm font-semibold">{merchant?.name}</p>
          <p className="text-xs text-[#cfe3e8]">{merchant?.effective_tier === 'BUSINESS_PLUS' ? 'Business Plus' : merchant?.effective_tier === 'PRO' ? 'Pro Merchant' : 'Free plan'}</p>
        </div>
      </div>
      <nav className="no-scrollbar flex-1 space-y-0.5 overflow-y-auto px-3" aria-label="Dashboard">
        {NAV.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.end}
            onClick={onNavigate}
            className={({ isActive }) => cn(
              'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition [&>svg]:size-4 [&>svg]:shrink-0',
              isActive ? 'bg-white/[0.12] text-white' : 'text-[#cfe3e8] hover:bg-white/[0.06] hover:text-white',
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
            <span className="size-2 rounded-full bg-emerald-400" aria-hidden />
          </div>
          {!unlimited && usage && (
            <>
              <p className="tabular mt-1.5 text-sm font-semibold">{usage.free_used} / {usage.monthly_quota} used</p>
              <div className="mt-2 h-1.5 rounded-full bg-white/10"><div className="h-1.5 rounded-full bg-emerald-400" style={{ width: `${Math.min(100, (usage.free_used / Math.max(1, usage.monthly_quota ?? 1)) * 100)}%` }} /></div>
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
    try { await apiPost('/auth/logout') } finally {
      resetCsrf()
      queryClient.clear()
      setSession(null)
      navigate('/login', { replace: true })
    }
  }

  return (
    <details className="relative">
      <summary className="flex cursor-pointer list-none items-center gap-2 rounded-lg p-1 hover:bg-surface-subtle [&::-webkit-details-marker]:hidden">
        <Avatar name={session.user.name} />
        <span className="hidden text-left text-sm leading-tight sm:block">
          <span className="block font-semibold text-ink-primary">{session.user.name}</span>
          <span className="block text-xs text-ink-muted">{session.user.role_label}</span>
        </span>
      </summary>
      <div className="absolute right-0 z-30 mt-2 w-56 rounded-xl border border-border-subtle bg-white p-1.5 shadow-lg">
        <p className="truncate px-3 py-2 text-xs text-ink-muted">{session.user.email}</p>
        <NavLink to="/dashboard/settings" className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-surface-subtle"><Settings className="size-4" />Settings</NavLink>
        <button type="button" onClick={logout} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50"><LogOut className="size-4" />Sign out</button>
      </div>
    </details>
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
          <div className="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
            <div className="flex items-center gap-3">
              <button type="button" onClick={() => setSidebarOpen(true)} className="inline-flex size-9 items-center justify-center rounded-lg hover:bg-surface-subtle lg:hidden" aria-label="Open navigation"><Menu className="size-5" /></button>
              {merchant && <StatusBadge status={merchant.record_mutability_mode} size="sm" showIcon className="hidden sm:inline-flex" />}
              {merchant?.status === 'SUSPENDED' && <StatusBadge status="SUSPENDED" size="sm" />}
            </div>
            <div className="flex items-center gap-2 sm:gap-3">
              <Button to="/dashboard/composer" size="sm" leftIcon={<Plus className="size-4" />}>New slip</Button>
              <UserMenu />
            </div>
          </div>
        </header>
        <main className="mx-auto w-full max-w-[1440px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
