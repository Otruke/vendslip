import { ArrowRight, Menu, X } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom'
import { Button, Container, Logo, Tagline } from '@/components/ui'
import { cn } from '@/lib/cn'
import { useAuthStore } from '@/stores/auth'

const links = [
  { to: '/how-it-works', label: 'How it works' },
  { to: '/pricing', label: 'Pricing' },
  { to: '/verify', label: 'Verify a slip' },
  { to: '/about', label: 'Trust & security' },
]

export function PublicNavbar() {
  const { pathname } = useLocation()
  // Menu is "open for" a specific path, so navigating closes it without an effect.
  const [openFor, setOpenFor] = useState<string | null>(null)
  const open = openFor === pathname
  const setOpen = (fn: (v: boolean) => boolean) => setOpenFor(fn(open) ? pathname : null)
  const [scrolled, setScrolled] = useState(false)
  const session = useAuthStore((s) => s.session)

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  return (
    <header className={cn('sticky top-0 z-40 border-b bg-white/85 backdrop-blur-md transition-colors', scrolled ? 'border-border-subtle' : 'border-transparent')}>
      <Container className="flex h-16 items-center justify-between gap-6">
        <Logo />
        <nav className="hidden items-center gap-1 md:flex" aria-label="Main">
          {links.map((l) => (
            <NavLink key={l.to} to={l.to} className={({ isActive }) => cn('rounded-lg px-3 py-2 text-sm font-medium transition', isActive ? 'text-netslip-deep' : 'text-ink-secondary hover:text-ink-primary')}>
              {l.label}
            </NavLink>
          ))}
        </nav>
        <div className="hidden items-center gap-2 md:flex">
          {session?.merchant ? (
            <Button to="/dashboard" size="sm" rightIcon={<ArrowRight className="size-4" />}>Dashboard</Button>
          ) : (
            <>
              <Button to="/login" variant="ghost" size="sm">Sign in</Button>
              <Button to="/register" size="sm">Start free</Button>
            </>
          )}
        </div>
        <button type="button" className="inline-flex size-10 items-center justify-center rounded-lg md:hidden" onClick={() => setOpen((v) => !v)} aria-label={open ? 'Close menu' : 'Open menu'} aria-expanded={open}>
          {open ? <X className="size-5" /> : <Menu className="size-5" />}
        </button>
      </Container>
      {open && (
        <div className="border-t border-border-subtle bg-white md:hidden">
          <Container className="flex flex-col gap-1 py-3">
            {links.map((l) => <Link key={l.to} to={l.to} className="rounded-lg px-3 py-2.5 text-sm font-medium text-ink-primary hover:bg-surface-subtle">{l.label}</Link>)}
            <div className="mt-2 grid grid-cols-2 gap-2">
              <Button to="/login" variant="outline">Sign in</Button>
              <Button to="/register">Start free</Button>
            </div>
          </Container>
        </div>
      )}
    </header>
  )
}

export function PublicFooter() {
  return (
    <footer className="bg-netslip-obsidian text-[#cfe3e8]">
      <Container className="grid gap-10 py-14 md:grid-cols-[1.4fr_1fr_1fr_1fr]">
        <div>
          <Logo variant="light" />
          <p className="mt-3 max-w-xs text-sm">Receipts and invoices for businesses that sell in chat.</p>
          <Tagline className="mt-4 block text-2xl text-white/80" />
        </div>
        {[
          ['Product', [['How it works', '/how-it-works'], ['Pricing', '/pricing'], ['Verify a slip', '/verify']]],
          ['Company', [['Trust & security', '/about'], ['Sign in', '/login'], ['Create account', '/register']]],
          ['Channels', [['WhatsApp', '/how-it-works'], ['Telegram', '/how-it-works'], ['Instagram & Facebook', '/how-it-works']]],
        ].map(([title, items]) => (
          <div key={title as string}>
            <p className="text-sm font-semibold text-white">{title as string}</p>
            <ul className="mt-3 space-y-2 text-sm">
              {(items as string[][]).map(([label, to]) => <li key={label}><Link to={to ?? '/'} className="hover:text-white">{label}</Link></li>)}
            </ul>
          </div>
        ))}
      </Container>
      <div className="border-t border-white/10">
        <Container className="flex flex-col gap-2 py-5 text-xs sm:flex-row sm:items-center sm:justify-between">
          <p>© {new Date().getFullYear()} NetSlip by Techberryz Futurecore Limited. Lagos, Nigeria.</p>
          <p>NDPA-aligned · HMAC-signed slips · Encrypted at rest</p>
        </Container>
      </div>
    </footer>
  )
}

export function PublicLayout() {
  const { pathname } = useLocation()
  // Block body on purpose: scrollTo returns a Promise in newer Chromium, and an effect may only return a cleanup function.
  useEffect(() => {
    window.scrollTo(0, 0)
  }, [pathname])
  return (
    <div className="flex min-h-dvh flex-col">
      <PublicNavbar />
      <main className="flex-1">
        <Outlet />
      </main>
      <PublicFooter />
    </div>
  )
}
