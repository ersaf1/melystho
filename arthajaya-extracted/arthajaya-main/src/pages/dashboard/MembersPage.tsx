import { Search, Filter, Plus, Phone, Calendar, Loader2, AlertCircle, X, User, MapPin, ToggleLeft, ToggleRight } from 'lucide-react'
import { Button, Input } from '../../components/ui/FormControls'
import { Modal } from '../../components/ui/Modal'
import { useState, useEffect } from 'react'
import { dataService } from '../../services/dataService'
import { useAuth } from '../../store/useAuth'
import { formatDate } from '../../lib/utils'
import { clsx } from 'clsx'
import { supabase } from '../../lib/supabase'
import { toast } from 'react-hot-toast'

export default function MembersPage() {
  const { profile } = useAuth()
  const [members, setMembers] = useState<any[]>([])
  const [loading, setLoading] = useState(true)
  const [searchTerm, setSearchTerm] = useState('')

  // Modal: detail anggota
  const [detailMember, setDetailMember] = useState<any>(null)

  // Modal: tambah anggota (admin only)
  const [isAddOpen, setIsAddOpen] = useState(false)
  const [addLoading, setAddLoading] = useState(false)
  const [addForm, setAddForm] = useState({
    full_name: '', email: '', password: '', phone: '', address: ''
  })

  async function loadData() {
    try {
      setLoading(true)
      const data = await dataService.getMembers()
      setMembers(data)
    } catch (err) {
      console.error(err)
      toast.error('Gagal memuat data anggota')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { loadData() }, [])

  const filteredMembers = members.filter(m =>
    m.profiles?.full_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    m.member_number?.toLowerCase().includes(searchTerm.toLowerCase())
  )

  async function handleToggleStatus(member: any) {
    const newStatus = member.status === 'active' ? 'inactive' : 'active'
    try {
      await dataService.updateMemberStatus(member.id, newStatus)
      toast.success(`Status anggota diubah ke ${newStatus}`)
      loadData()
    } catch {
      toast.error('Gagal mengubah status anggota')
    }
  }

  async function handleAddMember(e: React.FormEvent) {
    e.preventDefault()
    setAddLoading(true)
    try {
      // Buat user baru via Supabase Auth (trigger akan buat profile + member)
      const { error } = await supabase.auth.admin
        ? // Jika ada admin API, gunakan itu — fallback ke signUp biasa
          { error: new Error('use_signup') }
        : { error: new Error('use_signup') }

      // Fallback: gunakan signUp biasa (user perlu konfirmasi email jika diaktifkan)
      const { error: signUpError } = await supabase.auth.signUp({
        email: addForm.email,
        password: addForm.password,
        options: {
          data: {
            full_name: addForm.full_name,
            phone: addForm.phone,
            address: addForm.address,
            role: 'anggota',
          }
        }
      })
      if (signUpError) throw signUpError

      toast.success('Anggota baru berhasil ditambahkan')
      setIsAddOpen(false)
      setAddForm({ full_name: '', email: '', password: '', phone: '', address: '' })
      setTimeout(loadData, 1500) // tunggu trigger DB
    } catch (err: any) {
      toast.error(err.message || 'Gagal menambah anggota')
    } finally {
      setAddLoading(false)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-96">
        <Loader2 className="w-8 h-8 text-primary animate-spin" />
      </div>
    )
  }

  return (
    <div className="space-y-8 animate-in slide-in-from-top-4 duration-700">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold font-heading">Daftar Anggota</h1>
          <p className="text-slate-500">Koperasi memiliki <span className="text-white font-semibold">{members.length}</span> anggota terdaftar.</p>
        </div>
        {profile?.role === 'admin' && (
          <Button onClick={() => setIsAddOpen(true)}>
            <Plus size={18} className="mr-2" /> Tambah Anggota
          </Button>
        )}
      </div>

      {/* Search */}
      <div className="flex flex-col md:flex-row gap-4">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" size={18} />
          <input
            type="text"
            placeholder="Cari berdasarkan nama atau nomor anggota..."
            className="w-full bg-white/5 border border-white/10 rounded-xl py-3 pl-10 pr-4 outline-none focus:border-primary/50 transition-all"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
          />
        </div>
        <button className="flex items-center justify-center gap-2 px-4 py-3 bg-white/5 border border-white/10 rounded-xl hover:bg-white/10 transition-all text-slate-400">
          <Filter size={18} />
          <span>Filter</span>
        </button>
      </div>

      {/* Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {filteredMembers.length > 0 ? filteredMembers.map((member) => (
          <div key={member.id} className="glass-card p-6 space-y-5 group hover:border-primary/30 transition-all">
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-4">
                <div className="w-14 h-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center font-bold text-xl group-hover:bg-primary group-hover:text-white transition-all">
                  {member.profiles?.full_name?.charAt(0)}
                </div>
                <div>
                  <h3 className="font-bold text-lg leading-tight">{member.profiles?.full_name}</h3>
                  <p className="text-xs text-slate-500 font-mono uppercase tracking-wider">{member.member_number}</p>
                </div>
              </div>
              <span className={clsx(
                'px-2 py-1 rounded-full text-[10px] font-bold uppercase',
                member.status === 'active' ? 'bg-green-500/10 text-green-500' : 'bg-red-500/10 text-red-500'
              )}>
                {member.status === 'active' ? 'Aktif' : 'Nonaktif'}
              </span>
            </div>

            <div className="space-y-2 pt-3 border-t border-white/5">
              <div className="flex items-center gap-3 text-sm text-slate-400">
                <Phone size={15} className="text-slate-600 flex-shrink-0" />
                <span>{member.profiles?.phone || '-'}</span>
              </div>
              <div className="flex items-center gap-3 text-sm text-slate-400">
                <Calendar size={15} className="text-slate-600 flex-shrink-0" />
                <span>Bergabung {formatDate(member.join_date)}</span>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3 pt-2">
              <button
                onClick={() => setDetailMember(member)}
                className="py-2 text-xs font-bold bg-white/5 rounded-lg hover:bg-white/10 transition-all uppercase tracking-widest"
              >
                Detail
              </button>
              {profile?.role === 'admin' && (
                <button
                  onClick={() => handleToggleStatus(member)}
                  className={clsx(
                    'py-2 text-xs font-bold rounded-lg transition-all uppercase tracking-widest flex items-center justify-center gap-1',
                    member.status === 'active'
                      ? 'bg-red-500/10 text-red-400 hover:bg-red-500 hover:text-white'
                      : 'bg-green-500/10 text-green-400 hover:bg-green-500 hover:text-white'
                  )}
                >
                  {member.status === 'active' ? <ToggleRight size={14} /> : <ToggleLeft size={14} />}
                  {member.status === 'active' ? 'Nonaktifkan' : 'Aktifkan'}
                </button>
              )}
              {profile?.role === 'bendahara' && (
                <button
                  onClick={() => setDetailMember(member)}
                  className="py-2 text-xs font-bold bg-primary/10 text-primary rounded-lg hover:bg-primary hover:text-white transition-all uppercase tracking-widest"
                >
                  Simpanan
                </button>
              )}
            </div>
          </div>
        )) : (
          <div className="col-span-full py-20 text-center text-slate-600">
            <AlertCircle size={64} className="mx-auto mb-4 opacity-10" />
            <p className="text-lg font-medium">Anggota tidak ditemukan</p>
            <p className="text-sm">Coba kata kunci pencarian yang lain.</p>
          </div>
        )}
      </div>

      {/* Modal: Detail Anggota */}
      <Modal isOpen={!!detailMember} onClose={() => setDetailMember(null)} title="Detail Anggota">
        {detailMember && (
          <div className="space-y-6">
            <div className="flex items-center gap-4">
              <div className="w-16 h-16 rounded-2xl bg-primary/10 text-primary flex items-center justify-center font-bold text-2xl">
                {detailMember.profiles?.full_name?.charAt(0)}
              </div>
              <div>
                <h3 className="text-xl font-bold">{detailMember.profiles?.full_name}</h3>
                <p className="text-sm font-mono text-slate-400 uppercase">{detailMember.member_number}</p>
              </div>
            </div>

            <div className="space-y-3 p-4 rounded-xl bg-white/5">
              <InfoRow icon={User} label="Role" value={detailMember.profiles?.role} />
              <InfoRow icon={Phone} label="Telepon" value={detailMember.profiles?.phone || '-'} />
              <InfoRow icon={MapPin} label="Alamat" value={detailMember.profiles?.address || '-'} />
              <InfoRow icon={Calendar} label="Bergabung" value={formatDate(detailMember.join_date)} />
              <div className="flex items-center justify-between pt-2 border-t border-white/5">
                <span className="text-sm text-slate-500">Status</span>
                <span className={clsx(
                  'px-3 py-1 rounded-full text-xs font-bold uppercase',
                  detailMember.status === 'active' ? 'bg-green-500/10 text-green-500' : 'bg-red-500/10 text-red-500'
                )}>
                  {detailMember.status === 'active' ? 'Aktif' : 'Nonaktif'}
                </span>
              </div>
            </div>

            <Button variant="secondary" className="w-full" onClick={() => setDetailMember(null)}>
              Tutup
            </Button>
          </div>
        )}
      </Modal>

      {/* Modal: Tambah Anggota */}
      <Modal isOpen={isAddOpen} onClose={() => setIsAddOpen(false)} title="Tambah Anggota Baru">
        <form onSubmit={handleAddMember} className="space-y-4">
          <Input
            label="Nama Lengkap"
            placeholder="Budi Santoso"
            value={addForm.full_name}
            onChange={(e: any) => setAddForm({ ...addForm, full_name: e.target.value })}
            required
          />
          <Input
            label="Email"
            type="email"
            placeholder="budi@email.com"
            value={addForm.email}
            onChange={(e: any) => setAddForm({ ...addForm, email: e.target.value })}
            required
          />
          <Input
            label="Password Sementara"
            type="password"
            placeholder="Min. 6 karakter"
            value={addForm.password}
            onChange={(e: any) => setAddForm({ ...addForm, password: e.target.value })}
            required
          />
          <Input
            label="Nomor Telepon"
            placeholder="08123456789"
            value={addForm.phone}
            onChange={(e: any) => setAddForm({ ...addForm, phone: e.target.value })}
          />
          <Input
            label="Alamat"
            placeholder="Jl. Merdeka No. 1"
            value={addForm.address}
            onChange={(e: any) => setAddForm({ ...addForm, address: e.target.value })}
          />
          <div className="flex gap-3 pt-2">
            <Button variant="secondary" className="flex-1" type="button" onClick={() => setIsAddOpen(false)}>
              Batal
            </Button>
            <Button className="flex-1" type="submit" isLoading={addLoading}>
              Tambah Anggota
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  )
}

function InfoRow({ icon: Icon, label, value }: { icon: any; label: string; value: string }) {
  return (
    <div className="flex items-center justify-between">
      <div className="flex items-center gap-2 text-slate-500 text-sm">
        <Icon size={14} />
        <span>{label}</span>
      </div>
      <span className="text-sm font-medium capitalize">{value}</span>
    </div>
  )
}
