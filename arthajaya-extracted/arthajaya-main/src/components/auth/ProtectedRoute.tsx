import { Navigate, useLocation } from 'react-router-dom'
import { useAuth } from '../../store/useAuth'
import type { UserRole } from '../../services/authService'

interface ProtectedRouteProps {
  children: React.ReactNode
  allowedRoles?: UserRole[]
}

export default function ProtectedRoute({ children, allowedRoles }: ProtectedRouteProps) {
  const { user, profile, loading, initialized } = useAuth()
  const location = useLocation()

  // Still initializing auth — show a lightweight spinner
  if (!initialized) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-surface">
        <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary"></div>
      </div>
    )
  }

  // Auth is initialized but no user — redirect to login
  if (!user) {
    return <Navigate to="/login" state={{ from: location }} replace />
  }

  // Role-based access control
  if (allowedRoles && profile && !allowedRoles.includes(profile.role)) {
    return <Navigate to="/dashboard" replace />
  }

  return <>{children}</>
}
