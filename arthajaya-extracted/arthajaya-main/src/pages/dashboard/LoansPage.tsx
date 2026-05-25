import {
  HandCoins, Clock, CheckCircle2, AlertCircle, Plus, Loader2,
  ChevronDown, ChevronUp, CheckCheck, XCircle, CreditCard
} from 'lucide-react'
import { Button, Input, Select } from '../../components/ui/FormControls'
import { Modal } from '../../components/ui/Modal'
import { useState, useEffect } from 'react'
import { dataService } from '../../services/dataService'
import { useAuth } from '../../store/useAuth'
import { formatCurrency, formatDate } from '../../lib/utils'
import { toast } from 'react-hot-toast'
import { clsx } from 'clsx'

export default function LoansPage() {
  const { profile } = useAuth()
  const [loans, setLoans] = useState<any[]>([])
  const [loading, setLoading] = useState(true)
  const [memberId, setMemberId] = useState<string | null>(null)
  const [members, setMembers] = useState<any[]>([])

  // Expanded row untuk lihat angsuran
  const [expandedLoanId, setExpandedLoanId] = useState<string | null>(null)
  const [installments, setInstallments] = useState<Record<string, any[]>>({})
  const [loadingInstallments, setLoadingInstallments] = useState(false)

  // Modal pengajuan pinjaman (anggota)
  const [isApplyOpen, setIsApplyOpen] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [applyForm, setApplyForm] = useState({ amount: '', tenor: '12', interest_rate: '1.5' })

  // Modal bayar angsuran (staff)
  const [payingId, setPayingId] = useState<string | null>(null)

  const isStaff = profile?.role === 'admin' || profile?.role === 'bendahara'

  async function loadData() {
    try {
      setLoading(true)
      let mid: string | null = null

      if (profile?.role === 'anggota') {
        const member = await dataService.getMemberByUserId(profile.id)
        mid = member?.id || null
        setMemberId(mid)
      } else if (isStaff) {
        const list = await dataService.getMembers()
        setMembers(list)
      }

      const data = await dataService.getLoans(mid || undefined)
      setLoans(data)
    } catch (err) {
      console.error(err)
      toast.error('Gagal memuat data pinjaman')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { if (profile) loadData() }, [profile])

  async function toggleInstallments(loanId: string) {
    if (expandedLoanId === loanId) {
      setExpandedLoanId(null)
      return
    }
    setExpandedLoanId(loanId)
    if (installments[loanId]) return
    try {
      setLoadingInstallments(true)
      const data = await dataService.getInstallments(loanId)
      setInstallments(prev => ({ ...prev, [loanId]: data }))
    } catch {
      toast.error('Gagal memuat jadwal angsuran')
    } finally {
      setLoadingInstallments(false)
    }
  }

  async function handleApply(e: React.FormEvent) {
    e.preventDefault()
    if (!memberId) return
    try {
      setSubmitting(true)
      await dataService.createLoan({
        member_id: memberId,
        amount: Number(applyForm.amount),
        tenor: Number(applyForm.tenor),
        interest_rate: Number(applyForm.interest_rate),
        status: 'pending',
      })
      toast.success('Pengajuan pinjaman berhasil dikirim')
      setIsApplyOpen(false)
      setApplyForm({ amount: '', tenor: '12', interest_rate: '1.5' })
      await loadData()
    } catch (err: any) {
      toast.error(err.message || 'Gagal mengajukan pinjaman')
    } finally {
      setSubmitting(false)
    }
  }

  async function handleUpdateStatus(loanId: string, status: 'active' | 'rejected' | 'paid', loan?: any) {
    try {
      await dataService.updateLoanStatus(loanId, status)

      // Jika disetujui, generate jadwal angsuran
      if (status === 'active' && loan) {
        try {
          await dataService.generateInstallments({
            id: loanId,
            amount: loan.amount,
            interest_rate: loan.interest_rate,
            tenor: loan.tenor,
          })
        } catch {
          // Trigger DB mungkin sudah handle ini — abaikan duplikat
        }
      }

      const labels: Record<string, string> = {
        active: 'Pinjaman disetujui',
        rejected: 'Pinjaman ditolak',
        paid: 'Pinjaman ditandai lunas',
      }
      toast.success(labels[status])
      setInstallments({}) // reset cache angsuran
      await loadData()
    } catch (err: any) {
      toast.error(err.message || 'Gagal mengubah status pinjaman')
    }
  }

  async function handlePayInstallment(installmentId: string, loanId: string) {
    try {
      setPayingId(installmentId)
      await dataService.payInstallment(installmentId)
      toast.success('Angsuran berhasil dibayar')
      // Refresh installments for this loan
      const data = await dataService.getInstallments(loanId)
      setInstallments(prev => ({ ...prev, [loanId]: data }))
      await loadData()
    } catch (err: any) {
      toast.error(err.message || 'Gagal membayar angsuran')
    } finally {
      setPayingId(null)
    }
  }

  if (loading && loans.length === 0) {
    return (
      <div className="flex items-center justify-center h-96">
        <Loader2 className="w-8 h-8 text-primary animate-spin" />
      </div>
    )
  }

  const activeLoans = loans.filter(l => l.status === 'active')
  const totalLoanAmount = activeLoans.reduce((acc, curr) => acc + Number(curr.amount || 0), 0)
  const estimatedMonthly = Math.round(
    (Number(applyForm.amount) || 0) *
    (1 + (Number(applyForm.interest_rate) || 0) / 100) /
    (Number(applyForm.tenor) || 1)
  )

  return (
    <div className="space-y-8 animate-in slide-in-from-bottom-4 duration-700">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold font-heading">Pinjaman & Angsuran</h1>
          <p className="text-slate-500">Kelola pengajuan dan jadwal pembayaran pinjaman.</p>
        </div>
        {profile?.role === 'anggota' && (
          <Button onClick={() => setIsApplyOpen(true)}>
            <Plus size={18} className="mr-2" /> Ajukan Pinjaman
          </Button>
        )}
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <LoanStatsCard title="Total Pinjaman Aktif" value={formatCurrency(totalLoanAmount)} icon={HandCoins} color="text-primary" />
        <LoanStatsCard title="Menunggu Persetujuan" value={loans.filter(l => l.status === 'pending').length} icon={Clock} color="text-yellow-500" />
        <LoanStatsCard title="Pinjaman Lunas" value={loans.filter(l => l.status === 'paid').length} icon={CheckCircle2} color="text-green-500" />
      </div>

      {/* Tabel Pinjaman */}
      <div className="glass-card overflow-hidden">
        <div className="p-6 border-b border-white/5">
          <h3 className="text-lg font-semibold">Daftar Pinjaman</h3>
        </div>

        {loans.length === 0 ? (
          <div className="py-16 text-center text-slate-600">
            <AlertCircle size={48} className="mx-auto mb-3 opacity-20" />
            <p className="text-sm">Belum ada data pinjaman</p>
          </div>
        ) : (
          <div className="divide-y divide-white/5">
            {loans.map((loan) => (
              <div key={loan.id}>
                {/* Row utama */}
                <div className="px-6 py-4 flex flex-col md:flex-row md:items-center gap-4 hover:bg-white/5 transition-colors">
                  <div className="flex-1 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                      <p className="text-xs text-slate-500 mb-1">No. Anggota</p>
                      <p className="text-sm font-mono">{loan.members?.member_number || '-'}</p>
                    </div>
                    {isStaff && (
                      <div>
                        <p className="text-xs text-slate-500 mb-1">Nama</p>
                        <p className="text-sm font-medium">{loan.members?.profiles?.full_name || '-'}</p>
                      </div>
                    )}
                    <div>
                      <p className="text-xs text-slate-500 mb-1">Jumlah</p>
                      <p className="text-sm font-bold">{formatCurrency(loan.amount)}</p>
                    </div>
                    <div>
                      <p className="text-xs text-slate-500 mb-1">Tenor / Bunga</p>
                      <p className="text-sm">{loan.tenor} bln / {loan.interest_rate}%</p>
                    </div>
                    <div>
                      <p className="text-xs text-slate-500 mb-1">Tanggal</p>
                      <p className="text-sm">{formatDate(loan.created_at)}</p>
                    </div>
                  </div>

                  {/* Status + Aksi */}
                  <div className="flex items-center gap-3 flex-shrink-0">
                    <StatusBadge status={loan.status} />

                    {/* Tombol aksi staff */}
                    {isStaff && loan.status === 'pending' && (
                      <>
                        <button
                          onClick={() => handleUpdateStatus(loan.id, 'active', loan)}
                          className="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-green-500/10 text-green-400 hover:bg-green-500 hover:text-white text-xs font-bold transition-all"
                        >
                          <CheckCheck size={14} /> Setujui
                        </button>
                        <button
                          onClick={() => handleUpdateStatus(loan.id, 'rejected')}
                          className="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500 hover:text-white text-xs font-bold transition-all"
                        >
                          <XCircle size={14} /> Tolak
                        </button>
                      </>
                    )}
                    {isStaff && loan.status === 'active' && (
                      <button
                        onClick={() => handleUpdateStatus(loan.id, 'paid')}
                        className="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500 hover:text-white text-xs font-bold transition-all"
                      >
                        <CheckCircle2 size={14} /> Lunas
                      </button>
                    )}

                    {/* Toggle angsuran */}
                    {(loan.status === 'active' || loan.status === 'paid') && (
                      <button
                        onClick={() => toggleInstallments(loan.id)}
                        className="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white/5 text-slate-400 hover:bg-white/10 text-xs font-bold transition-all"
                      >
                        Angsuran
                        {expandedLoanId === loan.id ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
                      </button>
                    )}
                  </div>
                </div>

                {/* Jadwal Angsuran (expanded) */}
                {expandedLoanId === loan.id && (
                  <div className="bg-white/3 border-t border-white/5 px-6 py-4">
                    <h4 className="text-sm font-semibold mb-4 text-slate-300">Jadwal Angsuran</h4>
                    {loadingInstallments && !installments[loan.id] ? (
                      <div className="flex justify-center py-4">
                        <Loader2 className="w-5 h-5 text-primary animate-spin" />
                      </div>
                    ) : (
                      <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                          <thead>
                            <tr className="text-xs text-slate-500 uppercase">
                              <th className="text-left py-2 pr-4">Ke-</th>
                              <th className="text-left py-2 pr-4">Jumlah</th>
                              <th className="text-left py-2 pr-4">Tanggal Bayar</th>
                              <th className="text-left py-2 pr-4">Status</th>
                              {isStaff && <th className="text-right py-2">Aksi</th>}
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-white/5">
                            {(installments[loan.id] || []).map((inst: any) => (
                              <tr key={inst.id} className="hover:bg-white/5 transition-colors">
                                <td className="py-2 pr-4 font-mono text-slate-400">#{inst.installment_number}</td>
                                <td className="py-2 pr-4 font-semibold">{formatCurrency(inst.amount)}</td>
                                <td className="py-2 pr-4 text-slate-400">
                                  {inst.paid_at ? formatDate(inst.paid_at) : '-'}
                                </td>
                                <td className="py-2 pr-4">
                                  <InstallmentBadge status={inst.status} />
                                </td>
                                {isStaff && (
                                  <td className="py-2 text-right">
                                    {inst.status === 'unpaid' && (
                                      <button
                                        onClick={() => handlePayInstallment(inst.id, loan.id)}
                                        disabled={payingId === inst.id}
                                        className="flex items-center gap-1 ml-auto px-3 py-1 rounded-lg bg-primary/10 text-primary hover:bg-primary hover:text-white text-xs font-bold transition-all disabled:opacity-50"
                                      >
                                        {payingId === inst.id
                                          ? <Loader2 size={12} className="animate-spin" />
                                          : <CreditCard size={12} />
                                        }
                                        Bayar
                                      </button>
                                    )}
                                  </td>
                                )}
                              </tr>
                            ))}
                          </tbody>
                        </table>
                        {(installments[loan.id] || []).length === 0 && (
                          <p className="text-center text-slate-600 text-sm py-4">Belum ada jadwal angsuran</p>
                        )}
                      </div>
                    )}
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Modal Ajukan Pinjaman */}
      <Modal isOpen={isApplyOpen} onClose={() => setIsApplyOpen(false)} title="Formulir Pengajuan Pinjaman">
        <form onSubmit={handleApply} className="space-y-5">
          <Input
            label="Jumlah Pinjaman (Rp)"
            type="number"
            placeholder="Contoh: 5000000"
            value={applyForm.amount}
            onChange={(e: any) => setApplyForm({ ...applyForm, amount: e.target.value })}
            required
          />
          <div className="grid grid-cols-2 gap-4">
            <Select
              label="Tenor (Bulan)"
              value={applyForm.tenor}
              onChange={(e: any) => setApplyForm({ ...applyForm, tenor: e.target.value })}
            >
              <option value="6">6 Bulan</option>
              <option value="12">12 Bulan</option>
              <option value="24">24 Bulan</option>
              <option value="36">36 Bulan</option>
            </Select>
            <Input
              label="Bunga (%/bln)"
              type="number"
              step="0.1"
              value={applyForm.interest_rate}
              onChange={(e: any) => setApplyForm({ ...applyForm, interest_rate: e.target.value })}
              required
            />
          </div>

          {/* Estimasi */}
          <div className="p-4 rounded-xl bg-primary/5 border border-primary/10 space-y-2">
            <div className="flex justify-between text-sm">
              <span className="text-slate-500">Estimasi cicilan/bulan:</span>
              <span className="font-bold text-primary">{formatCurrency(estimatedMonthly)}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-slate-500">Total yang dikembalikan:</span>
              <span className="font-semibold">{formatCurrency(estimatedMonthly * Number(applyForm.tenor || 1))}</span>
            </div>
          </div>

          <div className="flex gap-3 pt-2">
            <Button variant="secondary" className="flex-1" type="button" onClick={() => setIsApplyOpen(false)}>Batal</Button>
            <Button className="flex-1" type="submit" isLoading={submitting}>Ajukan Sekarang</Button>
          </div>
        </form>
      </Modal>
    </div>
  )
}

function StatusBadge({ status }: { status: string }) {
  const map: Record<string, string> = {
    pending: 'bg-yellow-500/10 text-yellow-400',
    active: 'bg-blue-500/10 text-blue-400',
    paid: 'bg-green-500/10 text-green-400',
    rejected: 'bg-red-500/10 text-red-400',
  }
  const labels: Record<string, string> = {
    pending: 'Menunggu', active: 'Aktif', paid: 'Lunas', rejected: 'Ditolak'
  }
  return (
    <span className={clsx('px-2.5 py-1 rounded-full text-xs font-bold uppercase', map[status] || 'bg-white/10 text-slate-400')}>
      {labels[status] || status}
    </span>
  )
}

function InstallmentBadge({ status }: { status: string }) {
  const map: Record<string, string> = {
    unpaid: 'bg-slate-500/10 text-slate-400',
    paid: 'bg-green-500/10 text-green-400',
    late: 'bg-red-500/10 text-red-400',
  }
  const labels: Record<string, string> = { unpaid: 'Belum Bayar', paid: 'Lunas', late: 'Terlambat' }
  return (
    <span className={clsx('px-2 py-0.5 rounded-full text-[10px] font-bold uppercase', map[status] || '')}>
      {labels[status] || status}
    </span>
  )
}

function LoanStatsCard({ title, value, icon: Icon, color }: any) {
  return (
    <div className="glass-card p-6 flex items-center gap-5 group hover:border-primary/30 transition-all">
      <div className={`w-14 h-14 rounded-2xl bg-white/5 flex items-center justify-center ${color} group-hover:bg-primary/10 transition-all flex-shrink-0`}>
        <Icon size={28} />
      </div>
      <div>
        <p className="text-sm text-slate-500">{title}</p>
        <h4 className="text-2xl font-bold mt-1">{value}</h4>
      </div>
    </div>
  )
}
