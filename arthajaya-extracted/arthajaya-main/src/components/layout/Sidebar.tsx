import { NavLink, useNavigate } from 'react-router-dom'
import { 
  LayoutDashboard, 
  Users, 
  Wallet, 
  HandCoins, 
  History, 
  BarChart3, 
  LogOut,
  ChevronLeft,
  ChevronRight,
  X
} from 'lucide-react'
import { useAuth } from '../../store/useAuth'
import { useState } from 'react'
import { clsx } from 'clsx'

interface SidebarProps {
  isOpen?: boolean
  onClose?: () => void
}

export default function Sidebar({ isOpen, onClose }: SidebarProps) {
  const { profile, signOut } = useAuth()
  const [collapsed, setCollapsed] = useState(false)
  const navigate = useNavigate()

  async function handleLogout() {
    try {
      await signOut()
    } catch (err) {
      console.error('Logout error:', err)
    } finally {
      navigate('/login', { replace: true })
    }
  }

  const menuItems = [
    { 
      label: 'Beranda', 
      icon: LayoutDashboard, 
      path: '/dashboard', 
      roles: ['admin', 'bendahara', 'anggota'] 
    },
    { 
      label: 'Anggota', 
      icon: Users, 
      path: '/dashboard/members', 
      roles: ['admin', 'bendahara'] 
    },
    { 
      label: 'Simpanan', 
      icon: Wallet, 
      path: '/dashboard/savings', 
      roles: ['admin', 'bendahara', 'anggota'] 
    },
    { 
      label: 'Pinjaman', 
      icon: HandCoins, 
      path: '/dashboard/loans', 
      roles: ['admin', 'bendahara', 'anggota'] 
    },
    { 
      label: 'Transaksi', 
      icon: History, 
      path: '/dashboard/transactions', 
      roles: ['admin', 'bendahara'] 
    },
    { 
      label: 'Laporan', 
      icon: BarChart3, 
      path: '/dashboard/reports', 
      roles: ['admin'] 
    },
  ]

  const filteredItems = menuItems.filter(item => 
    profile && item.roles.includes(profile.role)
  )

  return (
    <>
      {/* Mobile Backdrop */}
      {isOpen && (
        <div 
          className="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 md:hidden"
          onClick={onClose}
        />
      )}

      <aside 
        className={clsx(
          "bg-surface-dark border-r border-white/5 h-screen flex flex-col transition-all duration-300 fixed md:relative z-50",
          collapsed ? "w-20" : "w-64",
          isOpen ? "translate-x-0" : "-translate-x-full md:translate-x-0"
        )}
      >
        <div className="p-6 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <img src="/logo.svg" alt="Logo" className="w-8 h-8" />
            {!collapsed && (
              <span className="text-xl font-bold tracking-tighter">ARTHAJAYA</span>
            )}
          </div>
          <button onClick={onClose} className="md:hidden text-slate-400">
            <X size={20} />
          </button>
        </div>

        <nav className="flex-1 px-3 space-y-1 mt-4">
          {filteredItems.map((item) => (
            <NavLink
              key={item.path}
              to={item.path}
              end={item.path === '/dashboard'}
              onClick={onClose}
              className={({ isActive }) => clsx(
                "flex items-center gap-3 px-3 py-3 rounded-xl transition-all duration-200 group",
                isActive 
                  ? "bg-primary/10 text-primary shadow-sm shadow-primary/10" 
                  : "text-slate-500 hover:bg-white/5 hover:text-slate-200"
              )}
            >
              <item.icon size={22} className="flex-shrink-0" />
              {!collapsed && <span className="font-medium">{item.label}</span>}
            </NavLink>
          ))}
        </nav>

        <div className="p-3 border-t border-white/5">
          <button
            onClick={handleLogout}
            className="w-full flex items-center gap-3 px-3 py-3 rounded-xl text-slate-500 hover:bg-red-500/10 hover:text-red-500 transition-all group"
          >
            <LogOut size={22} className="flex-shrink-0" />
            {!collapsed && <span className="font-medium">Keluar</span>}
          </button>
        </div>

        <button 
          onClick={() => setCollapsed(!collapsed)}
          className="absolute -right-3 top-20 w-6 h-6 bg-surface-light border border-white/10 rounded-full hidden md:flex items-center justify-center text-slate-400 hover:text-white transition-colors"
        >
          {collapsed ? <ChevronRight size={14} /> : <ChevronLeft size={14} />}
        </button>
      </aside>
    </>
  )
}
