import { Outlet } from 'react-router-dom'
import { useState } from 'react'
import Sidebar from './Sidebar'
import { Menu } from 'lucide-react'

export default function DashboardLayout() {
  const [isSidebarOpen, setIsSidebarOpen] = useState(false)

  return (
    <div className="flex min-h-screen bg-surface text-slate-200 overflow-hidden">
      <Sidebar isOpen={isSidebarOpen} onClose={() => setIsSidebarOpen(false)} />
      
      <div className="flex-1 flex flex-col h-screen overflow-hidden">
        <header className="h-20 border-b border-white/5 px-4 md:px-8 flex items-center justify-between bg-surface/50 backdrop-blur-md sticky top-0 z-10">
          <div className="flex items-center gap-4">
            <button 
              onClick={() => setIsSidebarOpen(true)}
              className="p-2 text-slate-400 hover:text-white md:hidden"
            >
              <Menu size={24} />
            </button>
            <div className="hidden md:block">
               <NavbarContent />
            </div>
          </div>
          
          <div className="md:hidden flex-1 px-4">
             <span className="text-lg font-bold tracking-tighter text-primary">ARTHAJAYA</span>
          </div>

          <div className="flex items-center gap-4">
             <NavbarActions />
          </div>
        </header>

        <main className="flex-1 overflow-y-auto p-4 md:p-8 custom-scrollbar">
          <div className="max-w-7xl mx-auto space-y-6 md:space-y-8">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  )
}

// Extracting Navbar content for cleaner layout
import { Bell, Search, User } from 'lucide-react'
import { useAuth } from '../../store/useAuth'

function NavbarContent() {
    return (
        <div className="relative max-w-md w-full group hidden md:block">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-primary transition-colors" size={18} />
          <input 
            type="text" 
            placeholder="Cari anggota, pinjaman, atau transaksi..."
            className="w-full bg-white/5 border border-white/5 rounded-xl py-2 pl-10 pr-4 text-sm focus:outline-none focus:border-primary/50 focus:ring-1 focus:ring-primary/50 transition-all"
          />
        </div>
    )
}

function NavbarActions() {
    const { profile } = useAuth()
    return (
        <div className="flex items-center gap-3 md:gap-6">
            <button className="relative p-2 text-slate-400 hover:text-white transition-colors">
            <Bell size={20} />
            <span className="absolute top-2 right-2 w-2 h-2 bg-primary rounded-full border-2 border-surface" />
            </button>

            <div className="flex items-center gap-3 md:pl-6 md:border-l border-white/10">
            <div className="text-right hidden sm:block">
                <p className="text-sm font-semibold">{profile?.full_name}</p>
                <p className="text-xs text-slate-500 capitalize">
                {profile?.role === 'admin' ? 'Administrator' : profile?.role === 'bendahara' ? 'Bendahara' : 'Anggota'}
                </p>
            </div>
            <div className="w-8 h-8 md:w-10 md:h-10 bg-surface-light rounded-full flex items-center justify-center border border-white/10 overflow-hidden">
                <User size={18} className="text-slate-400" />
            </div>
            </div>
        </div>
    )
}
