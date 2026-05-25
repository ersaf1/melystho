import { Routes, Route, Navigate } from 'react-router-dom'
import { useEffect, lazy, Suspense } from 'react'
import { useAuth } from './store/useAuth'
import ProtectedRoute from './components/auth/ProtectedRoute'
import DashboardLayout from './components/layout/DashboardLayout'

// Eagerly loaded pages (public, needed immediately)
import LandingPage from './pages/LandingPage'
import LoginPage from './pages/LoginPage'
import RegisterPage from './pages/RegisterPage'

// Lazy-loaded dashboard pages (only loaded when user navigates to them)
const OverviewPage = lazy(() => import('./pages/dashboard/OverviewPage'))
const MembersPage = lazy(() => import('./pages/dashboard/MembersPage'))
const SavingsPage = lazy(() => import('./pages/dashboard/SavingsPage'))
const LoansPage = lazy(() => import('./pages/dashboard/LoansPage'))

function PageLoader() {
  return (
    <div className="flex items-center justify-center h-96">
      <div className="animate-spin rounded-full h-10 w-10 border-t-2 border-b-2 border-primary"></div>
    </div>
  )
}

function App() {
  const { initialize } = useAuth()

  useEffect(() => {
    initialize()
  }, [initialize])

  return (
    <div className="min-h-screen flex flex-col">
      <Routes>
        {/* Public routes — render immediately, no auth blocking */}
        <Route path="/" element={<LandingPage />} />
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
        
        {/* Protected Dashboard Routes */}
        <Route 
          path="/dashboard" 
          element={
            <ProtectedRoute>
              <DashboardLayout />
            </ProtectedRoute>
          }
        >
          <Route index element={<Suspense fallback={<PageLoader />}><OverviewPage /></Suspense>} />
          <Route 
            path="members" 
            element={
              <ProtectedRoute allowedRoles={['admin', 'bendahara']}>
                <Suspense fallback={<PageLoader />}><MembersPage /></Suspense>
              </ProtectedRoute>
            } 
          />
          <Route path="savings" element={<Suspense fallback={<PageLoader />}><SavingsPage /></Suspense>} />
          <Route path="loans" element={<Suspense fallback={<PageLoader />}><LoansPage /></Suspense>} />
          <Route
            path="transactions"
            element={
              <ProtectedRoute allowedRoles={['admin', 'bendahara']}>
                <Suspense fallback={<PageLoader />}><SavingsPage /></Suspense>
              </ProtectedRoute>
            }
          />
          <Route
            path="reports"
            element={
              <ProtectedRoute allowedRoles={['admin']}>
                <Suspense fallback={<PageLoader />}><OverviewPage /></Suspense>
              </ProtectedRoute>
            }
          />
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </div>
  )
}

export default App
