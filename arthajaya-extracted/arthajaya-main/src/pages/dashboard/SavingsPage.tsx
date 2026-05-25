import { Wallet, ArrowDownLeft, ArrowUpRight, Plus, Loader2, Trash2 } from 'lucide-react'
import { Button, Input, Select, Textarea } from '../../components/ui/FormControls'
import { Modal } from '../../components/ui/Modal'
import { useState, useEffect } from 'react'
import { dataService } from '../../services/dataService'
import { useAuth } from '../../store/useAuth'
import { formatCurrency, formatDate } from '../../lib/utils'
import { toast } from 'react-hot-toast'
import { clsx } from 'clsx'

export default function SavingsPage() {
  const { profile } = useAuth()
  const [summary, setSummary] = useState<any>(null)
  const [loading, setLoading] = useState(true)
  const [memberId, setMemberId] = useState<string | null>(null)

  // Untuk admin/bendahara: pilih anggota
  const [members, setMembers] = useState<any[]>([])
  const [selectedMemberId, setSelectedMemberId] = useState<string>('')

  // Modal setoran
  const [isDepositOpen, setIsDepositOpen] = useState(false)
  const [isWithdrawOpen, setIsWithdrawOpen] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [txForm, setTxForm] = useState({
    type: 'wajib' as 'pokok' | 'wajib' | 'sukarela',
    amount: '',
    description: '',
  })

  const isStaff = profile?.role === 'admin' || profile?.role === 'bendahara'

  async function loadData(overrideMemberId?: string) {
    try {
      setLoading(true)
      let mid: string | null = null

      if (profile?.role === 'anggota') {
        const member = await dataService.getMemberByUserId(profile.id)
        mid = member?.id || null
        setMemberId(mid)
      } else if (isStaff) {
        // Load daftar anggota untuk dropdown
        const list = await dataService.getMembers()
        setMembers(list)
        mid = overrideMemberId || selectedMemberId || null
      }

      const s = await dataService.getSavingsSummary(mid || undefined)
      setSummary(s)
    } catch (err) {
      console.error(err)
      toast.error('Gagal memuat data simpanan')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { if (profile) loadData() }, [profile])

  async function handleMemberChange(id: string) {
    setSelectedMemberId(id)
    await loadData(id)
  }

  async function handleTransaction(txType: 'deposit' | 'withdrawal') {
    const targetId = profile?.role === 'anggota' ? memberId : selectedMemberId
    if (!targetId) {
      toast.error('Pilih anggota terlebih dahulu')
      return
    }
    if (!txForm.amount || Number(txForm.amount) <= 0) {
      toast.error('Jumlah harus lebih dari 0')
      return
    }

    try {
      setSubmitting(true)
      await dataService.createSavings({
        member_id: targetId,
        type: txForm.type,
        amount: Number(txForm.amount),
        transaction_type: txType,
        description: txForm.description || undefined,
      })
      toast.success(txType === 'deposit' ? 'Setoran berhasil dicatat' : 'Penarikan berhasil dicatat')
      setIsDepositOpen(false)
      setIsWithdrawOpen(false)
      setTxForm({ type: 'wajib', amount: '', description: '' })
      await loadData(selectedMemberId || undefined)
    } catch (err: any) {
      toast.error(err.message || 'Gagal menyimpan transaksi')
    } finally {
      setSubmitting(false)
    }
  }

  async function handleDelete(id: string) {
    if (!confirm('Hapus transaksi ini?')) return
    try {
      await dataService.deleteSavings(id)
      toast.success('Transaksi dihapus')
      await loadData(selectedMemberId || undefined)
    } catch {
      toast.error('Gagal menghapus transaksi')
    }
  }

  if (loading && !summary) {
    return (
      <div className="flex items-center justify-center h-96">
        <Loader2 className="w-8 h-8 text-primary animate-spin" />
      </div>
    )
  }

  const savingTypes = [
    { key: 'pokok', name: 'Simpanan Pokok', balance: summary?.pokok || 0, desc: 'Simpanan wajib saat awal menjadi anggota', color: 'bg-blue-500' },
    { key: 'wajib', name: 'Simpanan Wajib', balance: summary?.wajib || 0, desc: 'Simpanan rutin setiap bulan', color: 'bg-primary' },
    { key: 'sukarela', name: 'Simpanan Sukarela', balance: summary?.sukarela || 0, desc: 'Simpanan bebas yang bisa diambil kapan saja', color: 'bg-green-500' },
  ]

  const deposits = summary?.transactions?.filter((t: any) => t.transaction_type === 'deposit') || []
  const withdrawals = summary?.transactions?.filter((t: any) => t.transaction_type === 'withdrawal') || []

  return (
    <div className="space-y-8 animate-in slide-in-from-right-4 duration-700">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold font-heading">Ringkasan Simpanan</h1>
          <p className="text-slate-500">Pantau dan kelola seluruh simpanan koperasi.</p>
        </div>
        <div className="flex gap-2">
          {(isStaff || profile?.role === 'anggota') && (
            <Button onClick={() => setIsDepositOpen(true)}>
              <Plus size={18} className="mr-2" /> Setoran
            </Button>
          )}
          {isStaff && (
            <Button variant="secondary" onClick={() => setIsWithdrawOpen(true)}>
              <ArrowUpRight size={18} className="mr-2" /> Penarikan
            </Button>
          )}
        </div>
      </div>

      {/* Pilih anggota (staff only) */}
      {isStaff && (
        <div className="glass-card p-4">
          <label className="text-xs font-bold uppercase tracking-widest text-slate-500 block mb-2">
            Filter per Anggota
          </label>
          <select
            className="w-full md:w-80 bg-surface-dark border border-white/10 rounded-xl py-2.5 px-4 text-white focus:outline-none focus:border-primary/50 transition-all"
            value={selectedMemberId}
            onChange={(e) => handleMemberChange(e.target.value)}
          >
            <option value="">— Semua Anggota —</option>
            {members.map((m) => (
              <option key={m.id} value={m.id}>
                {m.profiles?.full_name} ({m.member_number})
              </option>
            ))}
          </select>
        </div>
      )}

      {/* Saldo Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {savingTypes.map((type) => (
          <div key={type.key} className="glass-card p-6 relative overflow-hidden group">
            <div className={`absolute top-0 right-0 w-32 h-32 ${type.color}/10 rounded-full -mr-16 -mt-16 blur-2xl transition-all group-hover:scale-150`} />
            <div className="space-y-4 relative z-10">
              <div className={`w-10 h-10 rounded-lg ${type.color}/20 flex items-center justify-center`}>
                <Wallet size={20} className="text-white" />
              </div>
              <div>
                <p className="text-sm text-slate-500">{type.name}</p>
                <h3 className={clsx('text-2xl font-bold', type.balance < 0 && 'text-red-400')}>
                  {formatCurrency(type.balance)}
                </h3>
              </div>
              <p className="text-xs text-slate-600">{type.desc}</p>
            </div>
          </div>
        ))}
      </div>

      {/* Riwayat */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* Setoran */}
        <div className="glass-card overflow-hidden">
          <div className="p-5 border-b border-white/5 flex items-center justify-between">
            <h3 className="text-lg font-semibold">Setoran Terbaru</h3>
            <span className="text-xs text-slate-500">{deposits.length} transaksi</span>
          </div>
          <div className="divide-y divide-white/5 max-h-[400px] overflow-y-auto custom-scrollbar">
            {deposits.length > 0 ? deposits.map((t: any) => (
              <div key={t.id} className="p-4 flex items-center justify-between hover:bg-white/5 transition-colors group/row">
                <div className="flex items-center gap-3">
                  <div className="w-9 h-9 rounded-full bg-green-500/10 text-green-500 flex items-center justify-center flex-shrink-0">
                    <ArrowDownLeft size={16} />
                  </div>
                  <div>
                    <p className="text-sm font-medium capitalize">Simpanan {t.type}</p>
                    <p className="text-xs text-slate-500">{formatDate(t.created_at)}</p>
                    {t.description && <p className="text-xs text-slate-600 italic">{t.description}</p>}
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <p className="text-sm font-bold text-green-500">+{formatCurrency(t.amount)}</p>
                  {isStaff && (
                    <button
                      onClick={() => handleDelete(t.id)}
                      className="opacity-0 group-hover/row:opacity-100 p-1 text-slate-600 hover:text-red-500 transition-all"
                    >
                      <Trash2 size={14} />
                    </button>
                  )}
                </div>
              </div>
            )) : (
              <p className="p-8 text-center text-slate-600 text-sm">Belum ada setoran</p>
            )}
          </div>
        </div>

        {/* Penarikan */}
        <div className="glass-card overflow-hidden">
          <div className="p-5 border-b border-white/5 flex items-center justify-between">
            <h3 className="text-lg font-semibold">Penarikan Terbaru</h3>
            <span className="text-xs text-slate-500">{withdrawals.length} transaksi</span>
          </div>
          <div className="divide-y divide-white/5 max-h-[400px] overflow-y-auto custom-scrollbar">
            {withdrawals.length > 0 ? withdrawals.map((t: any) => (
              <div key={t.id} className="p-4 flex items-center justify-between hover:bg-white/5 transition-colors group/row">
                <div className="flex items-center gap-3">
                  <div className="w-9 h-9 rounded-full bg-red-500/10 text-red-500 flex items-center justify-center flex-shrink-0">
                    <ArrowUpRight size={16} />
                  </div>
                  <div>
                    <p className="text-sm font-medium capitalize">Penarikan {t.type}</p>
                    <p className="text-xs text-slate-500">{formatDate(t.created_at)}</p>
                    {t.description && <p className="text-xs text-slate-600 italic">{t.description}</p>}
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <p className="text-sm font-bold text-red-500">-{formatCurrency(t.amount)}</p>
                  {isStaff && (
                    <button
                      onClick={() => handleDelete(t.id)}
                      className="opacity-0 group-hover/row:opacity-100 p-1 text-slate-600 hover:text-red-500 transition-all"
                    >
                      <Trash2 size={14} />
                    </button>
                  )}
                </div>
              </div>
            )) : (
              <p className="p-8 text-center text-slate-600 text-sm">Belum ada penarikan</p>
            )}
          </div>
        </div>
      </div>

      {/* Modal Setoran */}
      <Modal isOpen={isDepositOpen} onClose={() => setIsDepositOpen(false)} title="Catat Setoran Simpanan">
        <div className="space-y-5">
          {isStaff && (
            <div className="space-y-1.5">
              <label className="text-xs font-bold uppercase tracking-widest text-slate-500">Anggota</label>
              <select
                className="w-full bg-surface-dark border border-white/10 rounded-xl py-2.5 px-4 text-white focus:outline-none focus:border-primary/50 transition-all"
                value={selectedMemberId}
                onChange={(e) => setSelectedMemberId(e.target.value)}
                required
              >
                <option value="">— Pilih Anggota —</option>
                {members.map((m) => (
                  <option key={m.id} value={m.id}>
                    {m.profiles?.full_name} ({m.member_number})
                  </option>
                ))}
              </select>
            </div>
          )}
          <Select
            label="Jenis Simpanan"
            value={txForm.type}
            onChange={(e: any) => setTxForm({ ...txForm, type: e.target.value })}
          >
            <option value="pokok">Simpanan Pokok</option>
            <option value="wajib">Simpanan Wajib</option>
            <option value="sukarela">Simpanan Sukarela</option>
          </Select>
          <Input
            label="Jumlah (Rp)"
            type="number"
            placeholder="Contoh: 100000"
            value={txForm.amount}
            onChange={(e: any) => setTxForm({ ...txForm, amount: e.target.value })}
            required
          />
          <Textarea
            label="Keterangan (opsional)"
            placeholder="Catatan tambahan..."
            value={txForm.description}
            onChange={(e: any) => setTxForm({ ...txForm, description: e.target.value })}
          />
          <div className="flex gap-3 pt-2">
            <Button variant="secondary" className="flex-1" onClick={() => setIsDepositOpen(false)}>Batal</Button>
            <Button className="flex-1" isLoading={submitting} onClick={() => handleTransaction('deposit')}>
              Simpan Setoran
            </Button>
          </div>
        </div>
      </Modal>

      {/* Modal Penarikan */}
      <Modal isOpen={isWithdrawOpen} onClose={() => setIsWithdrawOpen(false)} title="Catat Penarikan Simpanan">
        <div className="space-y-5">
          <div className="space-y-1.5">
            <label className="text-xs font-bold uppercase tracking-widest text-slate-500">Anggota</label>
            <select
              className="w-full bg-surface-dark border border-white/10 rounded-xl py-2.5 px-4 text-white focus:outline-none focus:border-primary/50 transition-all"
              value={selectedMemberId}
              onChange={(e) => setSelectedMemberId(e.target.value)}
              required
            >
              <option value="">— Pilih Anggota —</option>
              {members.map((m) => (
                <option key={m.id} value={m.id}>
                  {m.profiles?.full_name} ({m.member_number})
                </option>
              ))}
            </select>
          </div>
          <Select
            label="Jenis Simpanan"
            value={txForm.type}
            onChange={(e: any) => setTxForm({ ...txForm, type: e.target.value })}
          >
            <option value="sukarela">Simpanan Sukarela</option>
            <option value="wajib">Simpanan Wajib</option>
            <option value="pokok">Simpanan Pokok</option>
          </Select>
          <Input
            label="Jumlah (Rp)"
            type="number"
            placeholder="Contoh: 100000"
            value={txForm.amount}
            onChange={(e: any) => setTxForm({ ...txForm, amount: e.target.value })}
            required
          />
          <Textarea
            label="Keterangan (opsional)"
            placeholder="Alasan penarikan..."
            value={txForm.description}
            onChange={(e: any) => setTxForm({ ...txForm, description: e.target.value })}
          />
          <div className="flex gap-3 pt-2">
            <Button variant="secondary" className="flex-1" onClick={() => setIsWithdrawOpen(false)}>Batal</Button>
            <Button variant="outline" className="flex-1" isLoading={submitting} onClick={() => handleTransaction('withdrawal')}>
              Simpan Penarikan
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
