import { ArrowRight, Wallet, Zap, BarChart3, Users, Menu, X, Shield, Globe, Activity } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Button } from '../components/ui/FormControls'
import FinJChat from '../components/ui/FinJChat'
import { useState } from 'react'

export default function LandingPage() {
  const [isMenuOpen, setIsMenuOpen] = useState(false)

  const scrollTo = (id: string) => {
    const element = document.getElementById(id);
    element?.scrollIntoView({ behavior: 'smooth' });
    setIsMenuOpen(false)
  };

  return (
    <div className="min-h-screen bg-surface selection:bg-primary/30 overflow-x-hidden">
      {/* Navigation */}
      <nav className="fixed top-0 w-full z-50 bg-surface/80 backdrop-blur-xl border-b border-white/5">
        <div className="max-w-7xl mx-auto px-4 md:px-6 h-20 flex items-center justify-between">
          <div className="flex items-center gap-2 md:gap-3">
            <img src="/logo.svg" alt="ARTHAJAYA" className="w-8 h-8 md:w-10 md:h-10" />
            <span className="text-xl md:text-2xl font-bold tracking-tighter text-white">ARTHAJAYA</span>
          </div>
          
          {/* Desktop Menu */}
          <div className="hidden md:flex items-center gap-8 text-sm font-medium text-slate-400">
            <button onClick={() => scrollTo('home')} className="hover:text-primary transition-colors cursor-pointer">Home</button>
            <button onClick={() => scrollTo('fitur')} className="hover:text-primary transition-colors cursor-pointer">Fitur</button>
            <button onClick={() => scrollTo('solusi')} className="hover:text-primary transition-colors cursor-pointer">Solusi</button>
            <button onClick={() => scrollTo('tentang')} className="hover:text-primary transition-colors cursor-pointer">Tentang</button>
          </div>

          <div className="hidden md:flex items-center gap-4">
            <Link to="/login" className="text-sm font-medium text-slate-400 hover:text-white transition-colors">Masuk</Link>
            <Link to="/register">
              <Button variant="primary" className="px-6">Bergabung Sekarang</Button>
            </Link>
          </div>

          {/* Mobile Menu Button */}
          <button 
            className="md:hidden p-2 text-slate-400 hover:text-white"
            onClick={() => setIsMenuOpen(!isMenuOpen)}
          >
            {isMenuOpen ? <X size={24} /> : <Menu size={24} />}
          </button>
        </div>

        {/* Mobile Sidebar Menu */}
        {isMenuOpen && (
          <div className="md:hidden absolute top-20 left-0 w-full bg-surface border-b border-white/5 p-6 space-y-6 animate-in slide-in-from-top duration-300">
            <div className="flex flex-col gap-4 text-lg font-medium text-slate-300">
              <button onClick={() => scrollTo('home')} className="text-left py-2 border-b border-white/5">Home</button>
              <button onClick={() => scrollTo('fitur')} className="text-left py-2 border-b border-white/5">Fitur</button>
              <button onClick={() => scrollTo('solusi')} className="text-left py-2 border-b border-white/5">Solusi</button>
              <button onClick={() => scrollTo('tentang')} className="text-left py-2 border-b border-white/5">Tentang</button>
            </div>
            <div className="flex flex-col gap-3">
              <Link to="/login" onClick={() => setIsMenuOpen(false)}>
                <Button variant="secondary" className="w-full h-12">Masuk</Button>
              </Link>
              <Link to="/register" onClick={() => setIsMenuOpen(false)}>
                <Button variant="primary" className="w-full h-12">Bergabung Sekarang</Button>
              </Link>
            </div>
          </div>
        )}
      </nav>

      {/* Hero Section */}
      <section id="home" className="relative pt-32 pb-20 md:pt-48 md:pb-32 px-4 md:px-6 overflow-hidden">
        <div className="absolute top-0 right-0 w-[300px] md:w-[600px] h-[300px] md:h-[600px] bg-primary/10 rounded-full blur-[80px] md:blur-[120px] -mr-32 -mt-32 md:-mr-64 md:-mt-64" />
        
        <div className="max-w-7xl mx-auto text-center relative z-10">
          <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/5 border border-white/10 text-primary text-[10px] md:text-xs font-bold tracking-widest uppercase mb-6 md:mb-8">
            <Zap size={12} /> Koperasi Masa Depan Telah Tiba
          </div>
          <h1 className="text-4xl md:text-7xl lg:text-8xl font-bold tracking-tighter mb-6 md:mb-8 bg-clip-text text-transparent bg-gradient-to-b from-white via-white/80 to-white/40 leading-[1.1]">
            Sistem Manajemen <br className="hidden md:block" /> Koperasi <span className="text-primary">Eksklusif.</span>
          </h1>
          <p className="text-base md:text-xl text-slate-400 max-w-2xl mx-auto mb-8 md:mb-12 font-light leading-relaxed px-4">
            Solusi infrastruktur finansial terintegrasi untuk pengelolaan koperasi yang aman, akuntabel, dan berstandar institusi.
          </p>
          <div className="flex flex-col sm:flex-row items-center justify-center gap-4 px-4">
            <Link to="/register" className="w-full sm:w-auto">
              <Button className="w-full sm:w-auto h-12 md:h-14 px-8 md:px-10 text-base md:text-lg group">
                Bergabung Sekarang <ArrowRight className="ml-2 group-hover:translate-x-1 transition-transform" size={18} />
              </Button>
            </Link>
            <button 
              onClick={() => scrollTo('solusi')} 
              className="btn-secondary w-full sm:w-auto h-12 md:h-14 px-8 md:px-10 text-base md:text-lg"
            >
              Lihat Solusi
            </button>
          </div>
        </div>
      </section>

      {/* Features Grid */}
      <section id="fitur" className="py-20 md:py-32 px-4 md:px-6 bg-surface-dark/50">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-12 md:mb-20">
            <div className="inline-block p-3 rounded-2xl bg-primary/10 text-primary mb-6">
              <BarChart3 size={32} />
            </div>
            <h2 className="text-3xl md:text-4xl font-bold mb-4">Fitur Utama ARTHAJAYA</h2>
            <p className="text-slate-500 max-w-2xl mx-auto text-base md:text-lg">Segala kebutuhan koperasi Anda dalam satu dashboard canggih.</p>
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">
            <FeatureCard 
              icon={Wallet} 
              title="Manajemen Simpanan" 
              desc="Kelola simpanan pokok, wajib, dan sukarela anggota secara otomatis dengan riwayat transaksi yang rapi."
            />
            <FeatureCard 
              icon={Zap} 
              title="Sistem Pinjaman" 
              desc="Proses pengajuan hingga persetujuan pinjaman dengan perhitungan bunga dan tenor yang fleksibel."
            />
            <FeatureCard 
              icon={Users} 
              title="Portal Anggota" 
              desc="Akses mandiri bagi anggota untuk memantau saldo, sisa angsuran, dan mengajukan pinjaman baru."
            />
          </div>
        </div>
      </section>

      {/* Solusi Section */}
      <section id="solusi" className="py-20 md:py-32 px-4 md:px-6 relative">
        <div className="max-w-7xl mx-auto">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 md:gap-20 items-center">
            <div className="space-y-6 md:space-y-8">
              <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-bold uppercase tracking-widest">
                Solusi Digital
              </div>
              <h2 className="text-3xl md:text-5xl font-bold leading-tight">
                Solusi Modern untuk <br />
                <span className="text-primary">Koperasi Tradisional.</span>
              </h2>
              <p className="text-slate-400 text-base md:text-lg leading-relaxed">
                Kami mendefinisikan ulang cara koperasi beroperasi dengan teknologi cloud yang aman dan efisien, menghilangkan hambatan birokrasi dan meningkatkan transparansi.
              </p>
              
              <div className="space-y-6">
                <SolutionItem 
                  icon={Globe}
                  title="Digitalisasi Total"
                  desc="Beralih dari pembukuan manual ke sistem digital terintegrasi yang dapat diakses dari mana saja."
                />
                <SolutionItem 
                  icon={Activity}
                  title="Transparansi Real-time"
                  desc="Anggota dapat memantau saldo, simpanan, dan status pinjaman mereka secara langsung melalui portal."
                />
                <SolutionItem 
                  icon={Shield}
                  title="Keamanan Berlapis"
                  desc="Data Anda dilindungi dengan enkripsi standar industri dan sistem backup otomatis yang handal."
                />
              </div>
            </div>
            
            <div className="relative">
              <div className="absolute inset-0 bg-primary/20 rounded-full blur-[100px] animate-pulse" />
              <div className="relative glass-card p-4 md:p-8 overflow-hidden">
                <img 
                  src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&q=80&w=2426" 
                  alt="Dashboard Preview" 
                  className="rounded-xl border border-white/10 shadow-2xl"
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Tentang Section */}
      <section id="tentang" className="py-20 md:py-32 px-4 md:px-6 relative overflow-hidden">
        <div className="absolute bottom-0 left-0 w-[400px] h-[400px] bg-primary/5 rounded-full blur-[120px] -ml-48 -mb-48" />
        
        <div className="max-w-7xl mx-auto relative z-10">
          <div className="glass-card p-8 md:p-16 border-primary/20 bg-primary/5">
            <div className="max-w-3xl">
              <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-bold uppercase tracking-widest mb-6">
                Visi Kami
              </div>
              <h2 className="text-3xl md:text-5xl font-bold mb-8">Memberdayakan Ekonomi Kerakyatan Melalui Teknologi.</h2>
              <p className="text-slate-300 text-lg md:text-xl leading-relaxed mb-10 font-light">
                ARTHAJAYA lahir dari keyakinan bahwa koperasi adalah pilar ekonomi Indonesia yang harus diperkuat. Kami hadir untuk menyediakan infrastruktur digital yang memungkinkan koperasi bersaing di era modern tanpa kehilangan jati diri gotong royongnya.
              </p>
              
              <div className="grid grid-cols-1 md:grid-cols-3 gap-8 pt-8 border-t border-white/10">
                <div>
                  <p className="text-3xl md:text-4xl font-bold text-white mb-1">10k+</p>
                  <p className="text-slate-500 text-sm">Target Digitalisasi Koperasi</p>
                </div>
                <div>
                  <p className="text-3xl md:text-4xl font-bold text-white mb-1">100%</p>
                  <p className="text-slate-500 text-sm">Transparan & Akuntabel</p>
                </div>
                <div>
                  <p className="text-3xl md:text-4xl font-bold text-white mb-1">24/7</p>
                  <p className="text-slate-500 text-sm">Dukungan Sistem</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="py-12 md:py-20 px-4 md:px-6 border-t border-white/5 bg-surface-dark">
        <div className="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-8 md:gap-12 text-center md:text-left">
          <div className="space-y-4">
            <div className="flex items-center justify-center md:justify-start gap-3">
              <img src="/logo.svg" alt="Logo" className="w-8 h-8" />
              <span className="text-xl font-bold tracking-tighter">ARTHAJAYA</span>
            </div>
            <p className="text-sm text-slate-500 max-w-xs mx-auto md:mx-0">
              Membangun ekonomi kerakyatan melalui digitalisasi koperasi yang transparan dan akuntabel.
            </p>
          </div>

        </div>
        <div className="max-w-7xl mx-auto mt-16 md:mt-20 pt-8 border-t border-white/5 text-center text-[10px] md:text-xs text-slate-600">
          &copy; 2026 Koperasi ARTHAJAYA. Seluruh hak dilindungi.
        </div>
      </footer>

      {/* Fin-J Chat Widget */}
      <FinJChat />
    </div>
  )
}

function SolutionItem({ icon: Icon, title, desc }: any) {
  return (
    <div className="flex gap-4 group">
      <div className="flex-shrink-0 w-12 h-12 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-all duration-300">
        <Icon size={20} />
      </div>
      <div>
        <h4 className="text-lg font-bold mb-1">{title}</h4>
        <p className="text-slate-500 text-sm leading-relaxed">{desc}</p>
      </div>
    </div>
  )
}

function FeatureCard({ icon: Icon, title, desc }: any) {
  return (
    <div className="glass-card p-8 md:p-10 space-y-6 hover:-translate-y-2 transition-all duration-500 group text-left">
      <div className="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-all duration-500">
        <Icon size={24} className="md:hidden" />
        <Icon size={28} className="hidden md:block" />
      </div>
      <div className="space-y-3">
        <h3 className="text-lg md:text-xl font-bold">{title}</h3>
        <p className="text-slate-500 text-sm leading-relaxed">{desc}</p>
      </div>
    </div>
  )
}
