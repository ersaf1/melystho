import { create } from 'zustand'
import type { User } from '@supabase/supabase-js'
import { authService, type UserProfile } from '../services/authService'
import { supabase } from '../lib/supabase'

interface AuthState {
  user: User | null
  profile: UserProfile | null
  loading: boolean
  initialized: boolean
  setUser: (user: User | null, profile: UserProfile | null) => void
  initialize: () => Promise<void>
  signIn: (email: string, password: string) => Promise<void>
  signOut: () => Promise<void>
}

let _initialized = false

export const useAuth = create<AuthState>((set, get) => ({
  user: null,
  profile: null,
  loading: true,
  initialized: false,
  setUser: (user, profile) => set({ user, profile, loading: false }),
  initialize: async () => {
    // Prevent multiple initializations & duplicate listeners
    if (_initialized) return
    _initialized = true

    try {
      const { data: { session } } = await supabase.auth.getSession()
      if (session?.user) {
        const profile = await authService.getProfile(session.user.id)
        set({ user: session.user, profile, loading: false, initialized: true })
      } else {
        set({ user: null, profile: null, loading: false, initialized: true })
      }
    } catch (error) {
      console.error('Auth initialization error:', error)
      set({ user: null, profile: null, loading: false, initialized: true })
    }

    // Listen for auth changes (only once)
    supabase.auth.onAuthStateChange(async (event, session) => {
      if (event === 'SIGNED_IN' && session?.user) {
        try {
          const profile = await authService.getProfile(session.user.id)
          set({ user: session.user, profile, loading: false })
        } catch {
          set({ user: session.user, profile: null, loading: false })
        }
      } else if (event === 'SIGNED_OUT') {
        set({ user: null, profile: null, loading: false })
      }
    })
  },
  signIn: async (email, password) => {
    const { user } = await authService.signIn(email, password)
    if (user) {
      // Set user + profile synchronously so navigate('/dashboard') works without a race condition
      try {
        const profile = await authService.getProfile(user.id)
        set({ user, profile, loading: false })
      } catch (err) {
        // Profile row may not exist yet (trigger race). Still set user so ProtectedRoute passes.
        console.warn('Profile not found on signIn, falling back to user only:', err)
        set({ user, profile: null, loading: false })
      }
    }
  },
  signOut: async () => {
    // Clear local state immediately so UI updates without waiting for network
    set({ user: null, profile: null, loading: false })
    
    // Call Supabase signOut in background
    authService.signOut().catch((error) => {
      console.warn('Supabase signOut background error:', error)
    })
  }
}))
