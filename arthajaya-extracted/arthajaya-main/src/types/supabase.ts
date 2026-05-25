export type Json =
  | string
  | number
  | boolean
  | null
  | { [key: string]: Json | undefined }
  | Json[]

export type Database = {
  public: {
    Tables: {
      profiles: {
        Row: {
          id: string
          role: 'admin' | 'bendahara' | 'anggota'
          full_name: string
          phone: string | null
          address: string | null
          created_at: string
        }
        Insert: {
          id: string
          role?: 'admin' | 'bendahara' | 'anggota'
          full_name: string
          phone?: string | null
          address?: string | null
          created_at?: string
        }
        Update: {
          id?: string
          role?: 'admin' | 'bendahara' | 'anggota'
          full_name?: string
          phone?: string | null
          address?: string | null
          created_at?: string
        }
      }
      members: {
        Row: {
          id: string
          user_id: string
          member_number: string
          join_date: string
          status: 'active' | 'inactive'
          created_at: string
        }
        Insert: {
          id?: string
          user_id: string
          member_number: string
          join_date?: string
          status?: 'active' | 'inactive'
          created_at?: string
        }
        Update: {
          id?: string
          user_id?: string
          member_number?: string
          join_date?: string
          status?: 'active' | 'inactive'
          created_at?: string
        }
      }
      savings: {
        Row: {
          id: string
          member_id: string
          type: 'pokok' | 'wajib' | 'sukarela'
          amount: number
          transaction_type: 'deposit' | 'withdrawal'
          description: string | null
          created_at: string
        }
        Insert: {
          id?: string
          member_id: string
          type: 'pokok' | 'wajib' | 'sukarela'
          amount: number
          transaction_type: 'deposit' | 'withdrawal'
          description?: string | null
          created_at?: string
        }
        Update: {
          id?: string
          member_id?: string
          type?: 'pokok' | 'wajib' | 'sukarela'
          amount?: number
          transaction_type?: 'deposit' | 'withdrawal'
          description?: string | null
          created_at?: string
        }
      }
      loans: {
        Row: {
          id: string
          member_id: string
          amount: number
          interest_rate: number
          tenor: number
          status: 'pending' | 'active' | 'paid' | 'rejected'
          approved_at: string | null
          created_at: string
        }
        Insert: {
          id?: string
          member_id: string
          amount: number
          interest_rate: number
          tenor: number
          status?: 'pending' | 'active' | 'paid' | 'rejected'
          approved_at?: string | null
          created_at?: string
        }
        Update: {
          id?: string
          member_id?: string
          amount?: number
          interest_rate?: number
          tenor?: number
          status?: 'pending' | 'active' | 'paid' | 'rejected'
          approved_at?: string | null
          created_at?: string
        }
      }
      installments: {
        Row: {
          id: string
          loan_id: string
          installment_number: number
          amount: number
          penalty: number
          paid_at: string | null
          status: 'unpaid' | 'paid' | 'late'
          created_at: string
        }
        Insert: {
          id?: string
          loan_id: string
          installment_number: number
          amount: number
          penalty?: number
          paid_at?: string | null
          status?: 'unpaid' | 'paid' | 'late'
          created_at?: string
        }
        Update: {
          id?: string
          loan_id?: string
          installment_number?: number
          amount?: number
          penalty?: number
          paid_at?: string | null
          status?: 'unpaid' | 'paid' | 'late'
          created_at?: string
        }
      }
    }
  }
}
