import { supabase } from '../lib/supabase'

export const dataService = {
  // ─── HELPERS ────────────────────────────────────────────────────────────────

  async getMemberByUserId(userId: string): Promise<any> {
    const { data, error } = await supabase
      .from('members')
      .select('*')
      .eq('user_id', userId)
      .maybeSingle()
    if (error) throw error
    return data
  },

  // ─── OVERVIEW ───────────────────────────────────────────────────────────────

  async getStats(memberId?: string) {
    let membersCount = 0
    if (!memberId) {
      const { count } = await supabase
        .from('members')
        .select('*', { count: 'exact', head: true })
      membersCount = count || 0
    }

    let savingsQuery = supabase.from('savings').select('amount, transaction_type') as any
    if (memberId) savingsQuery = savingsQuery.eq('member_id', memberId)
    const { data: savings } = await savingsQuery

    const totalSavings = (savings as any[])?.reduce((acc: number, curr: any) =>
      curr.transaction_type === 'deposit' ? acc + Number(curr.amount) : acc - Number(curr.amount), 0) || 0

    let loansQuery = supabase.from('loans').select('amount').eq('status', 'active') as any
    if (memberId) loansQuery = loansQuery.eq('member_id', memberId)
    const { data: activeLoans } = await loansQuery

    const totalActiveLoans = (activeLoans as any[])?.reduce((acc: number, curr: any) => acc + Number(curr.amount), 0) || 0

    return { membersCount, totalSavings, totalActiveLoans, isPersonal: Boolean(memberId) }
  },

  async getRecentTransactions(limit = 5, memberId?: string): Promise<any[]> {
    let query = supabase
      .from('savings')
      .select('*, members(member_number, user_id, profiles(full_name))')
      .order('created_at', { ascending: false })
      .limit(limit) as any
    if (memberId) query = query.eq('member_id', memberId)
    const { data, error } = await query
    if (error) throw error
    return data || []
  },

  async getChartData(): Promise<any[]> {
    const { data, error } = await supabase
      .from('savings')
      .select('amount, transaction_type, created_at') as any
    if (error) return []

    const months: Record<string, { savings: number; loans: number }> = {}
    const monthNames = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des']

    ;(data as any[]).forEach((s: any) => {
      const d = new Date(s.created_at)
      const key = monthNames[d.getMonth()]
      if (!months[key]) months[key] = { savings: 0, loans: 0 }
      if (s.transaction_type === 'deposit') months[key].savings += Number(s.amount)
      else months[key].loans += Number(s.amount)
    })

    return Object.entries(months).map(([name, v]) => ({ name, ...v }))
  },

  // ─── MEMBERS ────────────────────────────────────────────────────────────────

  async getMembers(): Promise<any[]> {
    const { data, error } = await supabase
      .from('members')
      .select('*, profiles(full_name, role, phone, address)')
      .order('created_at', { ascending: false }) as any
    if (error) throw error
    return data || []
  },

  async getMemberDetail(memberId: string): Promise<any> {
    const { data, error } = await supabase
      .from('members')
      .select('*, profiles(full_name, role, phone, address)')
      .eq('id', memberId)
      .single() as any
    if (error) throw error
    return data
  },

  async updateMemberStatus(memberId: string, status: 'active' | 'inactive'): Promise<void> {
    const { error } = await supabase
      .from('members')
      .update({ status })
      .eq('id', memberId) as any
    if (error) throw error
  },

  // ─── SAVINGS ────────────────────────────────────────────────────────────────

  async getSavingsSummary(memberId?: string): Promise<any> {
    let query = supabase.from('savings').select('*').order('created_at', { ascending: false }) as any
    if (memberId) query = query.eq('member_id', memberId)
    const { data, error } = await query
    if (error) throw error

    const summary = { pokok: 0, wajib: 0, sukarela: 0, transactions: data as any[] }
    ;(data as any[]).forEach((s: any) => {
      const amount = s.transaction_type === 'deposit' ? Number(s.amount) : -Number(s.amount)
      summary[s.type as 'pokok' | 'wajib' | 'sukarela'] += amount
    })
    return summary
  },

  async createSavings(payload: {
    member_id: string
    type: 'pokok' | 'wajib' | 'sukarela'
    amount: number
    transaction_type: 'deposit' | 'withdrawal'
    description?: string
  }): Promise<any> {
    const { data, error } = await supabase.from('savings').insert([payload]).select() as any
    if (error) throw error
    return (data as any[])[0]
  },

  async deleteSavings(id: string): Promise<void> {
    const { error } = await supabase.from('savings').delete().eq('id', id) as any
    if (error) throw error
  },

  // ─── LOANS ──────────────────────────────────────────────────────────────────

  async getLoans(memberId?: string): Promise<any[]> {
    let query = supabase
      .from('loans')
      .select('*, members(member_number, profiles(full_name))')
      .order('created_at', { ascending: false }) as any
    if (memberId) query = query.eq('member_id', memberId)
    const { data, error } = await query
    if (error) throw error
    return data || []
  },

  async createLoan(payload: {
    member_id: string
    amount: number
    interest_rate: number
    tenor: number
    status?: 'pending' | 'active'
  }): Promise<any> {
    const { data, error } = await supabase.from('loans').insert([payload]).select() as any
    if (error) throw error
    return (data as any[])[0]
  },

  async updateLoanStatus(loanId: string, status: 'active' | 'rejected' | 'paid'): Promise<any> {
    const { data, error } = await supabase
      .from('loans')
      .update({
        status,
        approved_at: status === 'active' ? new Date().toISOString() : null,
      })
      .eq('id', loanId)
      .select() as any
    if (error) throw error
    return (data as any[])[0]
  },

  // ─── INSTALLMENTS ───────────────────────────────────────────────────────────

  async getInstallments(loanId: string): Promise<any[]> {
    const { data, error } = await supabase
      .from('installments')
      .select('*')
      .eq('loan_id', loanId)
      .order('installment_number', { ascending: true }) as any
    if (error) throw error
    return data || []
  },

  async payInstallment(installmentId: string): Promise<any> {
    const { data, error } = await supabase
      .from('installments')
      .update({ status: 'paid', paid_at: new Date().toISOString() })
      .eq('id', installmentId)
      .select() as any
    if (error) throw error
    return (data as any[])[0]
  },

  // Generate installments manually (called after approving a loan)
  async generateInstallments(loan: { id: string; amount: number; interest_rate: number; tenor: number }): Promise<void> {
    const monthly = Math.round((loan.amount * (1 + (loan.interest_rate / 100) * loan.tenor)) / loan.tenor)
    const rows = Array.from({ length: loan.tenor }, (_, i) => ({
      loan_id: loan.id,
      installment_number: i + 1,
      amount: monthly,
    }))
    const { error } = await supabase.from('installments').insert(rows) as any
    if (error) throw error
  },
}
