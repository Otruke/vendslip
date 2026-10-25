import { lazy, Suspense, type ReactNode } from 'react'
import { createBrowserRouter } from 'react-router-dom'
import { GuestOnly, RequireAuth, SessionProvider } from '@/components/layout/AuthGate'
import { PublicLayout } from '@/components/layout/PublicLayout'
import { PageLoader } from '@/components/ui'

const Landing = lazy(() => import('@/pages/public/Landing'))
const HowItWorks = lazy(() => import('@/pages/public/HowItWorks'))
const Pricing = lazy(() => import('@/pages/public/Pricing'))
const About = lazy(() => import('@/pages/public/About'))
const Verify = lazy(() => import('@/pages/public/Verify'))
const Pay = lazy(() => import('@/pages/public/Pay'))
const NotFound = lazy(() => import('@/pages/public/NotFound'))

const Login = lazy(() => import('@/pages/auth/Login'))
const Register = lazy(() => import('@/pages/auth/Register'))
const TwoFactor = lazy(() => import('@/pages/auth/TwoFactor'))
const ForgotPassword = lazy(() => import('@/pages/auth/ForgotPassword'))
const ResetPassword = lazy(() => import('@/pages/auth/ResetPassword'))
const AcceptInvite = lazy(() => import('@/pages/auth/AcceptInvite'))

const DashboardLayout = lazy(() => import('@/components/layout/DashboardLayout'))
const Onboarding = lazy(() => import('@/pages/dashboard/Onboarding'))
const Overview = lazy(() => import('@/pages/dashboard/Overview'))
const Composer = lazy(() => import('@/pages/dashboard/Composer'))
const Documents = lazy(() => import('@/pages/dashboard/Documents'))
const Templates = lazy(() => import('@/pages/dashboard/Templates'))
const Customers = lazy(() => import('@/pages/dashboard/Customers'))
const Inventory = lazy(() => import('@/pages/dashboard/Inventory'))
const Team = lazy(() => import('@/pages/dashboard/Team'))
const Channels = lazy(() => import('@/pages/dashboard/Channels'))
const Billing = lazy(() => import('@/pages/dashboard/Billing'))
const Settings = lazy(() => import('@/pages/dashboard/Settings'))

const page = (node: ReactNode) => <Suspense fallback={<PageLoader />}>{node}</Suspense>

export const router = createBrowserRouter([
  {
    element: <SessionProvider />,
    children: [
      {
        element: <PublicLayout />,
        children: [
          { path: '/', element: page(<Landing />) },
          { path: '/how-it-works', element: page(<HowItWorks />) },
          { path: '/pricing', element: page(<Pricing />) },
          { path: '/about', element: page(<About />) },
          { path: '/verify', element: page(<Verify />) },
          { path: '/verify/:code', element: page(<Verify />) },
          { path: '/pay/:uid', element: page(<Pay />) },
        ],
      },
      { path: '/login', element: page(<GuestOnly><Login /></GuestOnly>) },
      { path: '/register', element: page(<GuestOnly><Register /></GuestOnly>) },
      { path: '/verify-2fa', element: page(<TwoFactor />) },
      { path: '/forgot-password', element: page(<ForgotPassword />) },
      { path: '/reset-password', element: page(<ResetPassword />) },
      { path: '/accept-invite/:token', element: page(<AcceptInvite />) },
      {
        path: '/dashboard',
        element: <RequireAuth>{page(<DashboardLayout />)}</RequireAuth>,
        children: [
          { index: true, element: page(<Overview />) },
          { path: 'overview', element: page(<Overview />) },
          { path: 'onboarding', element: page(<Onboarding />) },
          { path: 'composer', element: page(<Composer />) },
          { path: 'documents', element: page(<Documents />) },
          { path: 'documents/:id', element: page(<Documents />) },
          { path: 'templates', element: page(<Templates />) },
          { path: 'customers', element: page(<Customers />) },
          { path: 'inventory', element: page(<Inventory />) },
          { path: 'team', element: page(<Team />) },
          { path: 'channels', element: page(<Channels />) },
          { path: 'billing', element: page(<Billing />) },
          { path: 'settings', element: page(<Settings />) },
        ],
      },
      { path: '*', element: page(<NotFound />) },
    ],
  },
])
