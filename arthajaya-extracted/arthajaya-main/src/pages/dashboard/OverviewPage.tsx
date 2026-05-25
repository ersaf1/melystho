import { useAuth } from '../../store/useAuth'
import { 
  Users, 
  Wallet, 
  HandCoins, 
  TrendingUp,
  ArrowUpRight,
  ArrowDownRight,
  Loader2
} from 'lucide-react'
import { 
  AreaChart, 
  Area, 
  XAxis, 
  YAxis, 
  CartesianGrid, 
  Tooltip, 
  ResponsiveContainer 
} from 'recharts'
import { clsx } from 'clsx'
import { useState, useEffect } from 'react'
import { dataService } from '../../services/dataService'
import { formatCurrency } from '../../lib/utils'
import { formatDistanceToNow } from 'date-fns'
import { id } from 'date-fns/locale'

const dummyData = [
  { name: 'Jan', savings: 4000, loans: 2400 },
  { name: 'Feb', savings: 3000, loans: 1398 },
  { name: 'Mar', savings: 2000, loans: 9800 },
  { name: 'Apr', savings: 2780, loans: 3908 },
  { name: 'May', savings: 1890, loans: 4800 },
  { name: 'Jun', savings: 2390, loans: 3800 },
]

export default function OverviewPage() {
  const { profile } = useAuth()
  const [stats, setStats] = useState<any>(null)
  const [transactions, setTransactions] = useState<any[]>([])
  const [chartData, setChartData] = useState<any[]>(dummyData)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    async function loadData() {
      try {
        let memberId: string | undefined
        if (profile?.role === 'anggota') {
          const member = await dataService.getMemberByUserId(profile.id)
          memberId = member?.id
        }

        const [s, t, c] = await Promise.all([
          dataService.getStats(memberId),
          dataService.getRecentTransactions(5, memberId),
          dataService.getChartData(),
        ])
        setStats(s)
        setTransactions(t)
        if (c.length > 0) setChartData(c)
      } catch (err) {
        console.error('Failed to load dashboard data:', err)
      } finally {
        setLoading(false)
      }
    }
    if (profile) loadData()
  }, [profile])

  if (loading) {
    return (
      <div className="flex items-center justify-center h-96">
        <Loader2 className="w-8 h-8 text-primary animate-spin" />
      </div>
    )
  }

  return (
    <div className="space-y-8 animate-in fade-in duration-700">
      <header>
        <h1 className="text-3xl font-bold font-heading">Selamat datang kembali, {profile?.full_name?.split(' ')[0] || 'Pengguna'}!</h1>
        <p className="text-slate-500">
          {stats?.isPersonal
            ? 'Berikut adalah ringkasan keuangan pribadi Anda di ARTHAJAYA.'
            : 'Berikut adalah ringkasan aktivitas ARTHAJAYA hari ini.'}
        </p>
      </header>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {!stats?.isPersonal && (
          <StatsCard 
            title="Total Anggota" 
            value={stats?.membersCount || 0} 
            change="+0%" 
            isPositive={true}
            icon={Users}
          />
        )}
        <StatsCard 
          title={stats?.isPersonal ? 'Saldo Simpanan Saya' : 'Total Simpanan'} 
          value={formatCurrency(stats?.totalSavings || 0)} 
          change="+0%" 
          isPositive={true}
          icon={Wallet}
        />
        <StatsCard 
          title={stats?.isPersonal ? 'Pinjaman Aktif Saya' : 'Pinjaman Aktif'} 
          value={formatCurrency(stats?.totalActiveLoans || 0)} 
          change="-0%" 
          isPositive={false}
          icon={HandCoins}
        />
        <StatsCard 
          title="Tingkat Pertumbuhan" 
          value="0%" 
          change="+0%" 
          isPositive={true}
          icon={TrendingUp}
        />
      </div>

      {/* Charts Section */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div className="lg:col-span-2 glass-card p-6 min-h-[400px] flex flex-col">
          <div className="flex items-center justify-between mb-8">
            <h3 className="text-lg font-semibold">Performa Keuangan</h3>
            <select className="bg-white/5 border border-white/10 rounded-lg px-3 py-1 text-sm outline-none cursor-pointer">
              <option>6 Bulan Terakhir</option>
              <option>1 Tahun Terakhir</option>
            </select>
          </div>
          <div className="flex-1 w-full">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={chartData}>
                <defs>
                  <linearGradient id="colorSavings" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#F97316" stopOpacity={0.3}/>
                    <stop offset="95%" stopColor="#F97316" stopOpacity={0}/>
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="rgba(255,255,255,0.05)" />
                <XAxis 
                  dataKey="name" 
                  axisLine={false} 
                  tickLine={false} 
                  tick={{ fill: '#64748b', fontSize: 12 }}
                  dy={10}
                />
                <YAxis 
                  axisLine={false} 
                  tickLine={false} 
                  tick={{ fill: '#64748b', fontSize: 12 }}
                />
                <Tooltip 
                  contentStyle={{ 
                    backgroundColor: '#1E293B', 
                    border: '1px solid rgba(255,255,255,0.1)',
                    borderRadius: '12px'
                  }} 
                />
                <Area 
                  type="monotone" 
                  dataKey="savings" 
                  stroke="#F97316" 
                  fillOpacity={1} 
                  fill="url(#colorSavings)" 
                  strokeWidth={3}
                />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="glass-card p-6 flex flex-col">
          <h3 className="text-lg font-semibold mb-6">Transaksi Terbaru</h3>
          <div className="flex-1 space-y-4">
            {transactions.length > 0 ? transactions.map((t) => (
              <div key={t.id} className="flex items-center justify-between p-3 rounded-xl hover:bg-white/5 transition-colors group cursor-pointer">
                <div className="flex items-center gap-4">
                  <div className="w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center text-primary group-hover:bg-primary/20 transition-colors">
                    <Wallet size={20} />
                  </div>
                  <div>
                    <p className="text-sm font-medium capitalize">Setoran {t.type}</p>
                    <p className="text-xs text-slate-500">
                      {formatDistanceToNow(new Date(t.created_at), { addSuffix: true, locale: id })}
                    </p>
                  </div>
                </div>
                <div className="text-right">
                  <p className={clsx(
                    "text-sm font-bold",
                    t.transaction_type === 'deposit' ? "text-green-500" : "text-red-500"
                  )}>
                    {t.transaction_type === 'deposit' ? '+' : '-'}{formatCurrency(t.amount)}
                  </p>
                  <p className="text-xs text-slate-500 font-mono uppercase">{t.members?.member_number}</p>
                </div>
              </div>
            )) : (
              <div className="flex flex-col items-center justify-center h-64 text-slate-600">
                <Wallet size={48} className="mb-2 opacity-20" />
                <p className="text-sm">Belum ada transaksi</p>
              </div>
            )}
          </div>
          <button className="w-full mt-6 py-2 text-sm text-slate-400 hover:text-white transition-colors">
            Lihat Semua Transaksi
          </button>
        </div>
      </div>
    </div>
  )
}

function StatsCard({ title, value, change, isPositive, icon: Icon }: any) {
  return (
    <div className="glass-card p-6 space-y-4 group">
      <div className="flex items-center justify-between">
        <div className="p-3 rounded-xl bg-white/5 text-slate-400 group-hover:bg-primary/10 group-hover:text-primary transition-all">
          <Icon size={24} />
        </div>
        <div className={clsx(
          "flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full",
          isPositive ? "bg-green-500/10 text-green-500" : "bg-red-500/10 text-red-500"
        )}>
          {isPositive ? <ArrowUpRight size={14} /> : <ArrowDownRight size={14} />}
          {change}
        </div>
      </div>
      <div>
        <p className="text-sm text-slate-500">{title}</p>
        <h4 className="text-2xl font-bold mt-1">{value}</h4>
      </div>
    </div>
  )
}
