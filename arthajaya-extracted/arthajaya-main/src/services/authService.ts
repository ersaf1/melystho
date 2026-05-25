import { supabase } from '../lib/supabase'

export type UserRole = 'admin' | 'bendahara' | 'anggota'

export interface UserProfile {
  id: string
  role: UserRole
  full_name: string
  phone?: string
  address?: string
}

export const authService = {
  async signIn(email: string, password: string) {
    const { data, error } = await supabase.auth.signInWithPassword({
      email,
      password,
    })
    if (error) throw error
    return data
  },

  async getProfile(userId: string) {
    const { data, error } = await supabase
      .from('profiles')
      .select('*')
      .eq('id', userId)
      .single()

    if (error) throw error
    return data as UserProfile
  },

  async signOut() {
    const { error } = await supabase.auth.signOut()
    if (error) throw error
  },

  async getCurrentUser() {
    const { data: { user } } = await supabase.auth.getUser()
    if (!user) return null
    
    const profile = await this.getProfile(user.id)
    return { ...user, profile }
  }
}
