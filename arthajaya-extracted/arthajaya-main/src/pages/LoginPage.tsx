import { useState } from 'react'
import { useNavigate, Link } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import * as z from 'zod'
import { useAuth } from '../store/useAuth'
import { Button, Input } from '../components/ui/FormControls'
import { toast } from 'react-hot-toast'
import { Mail, Lock, ArrowRight } from 'lucide-react'

const loginSchema = z.object({
  email: z.string().email('Alamat email tidak valid'),
  password: z.string().min(6, 'Kata sandi minimal 6 karakter'),
})

type LoginFormValues = z.infer<typeof loginSchema>

export default function LoginPage() {
  const navigate = useNavigate()
  const { signIn } = useAuth()
  const [isLoading, setIsLoading] = useState(false)

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
  })

  const onSubmit = async (data: LoginFormValues) => {
    setIsLoading(true)
    try {
      await signIn(data.email, data.password)
      toast.success('Selamat datang kembali!')
      navigate('/dashboard')
    } catch (error: any) {
      toast.error(error.message || 'Gagal masuk. Periksa email dan kata sandi Anda.')
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center p-4 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-primary/10 via-surface to-surface-dark">
      <div className="w-full max-w-md">
        <div className="text-center mb-10">
          <Link to="/" className="inline-block">
            <img src="/logo.svg" alt="Logo" className="w-12 h-12 mx-auto mb-4" />
          </Link>
          <h1 className="text-4xl font-bold text-primary mb-2 tracking-tighter">ARTHAJAYA</h1>
          <p className="text-slate-400">Dashboard Keuangan Koperasi</p>
        </div>

        <div className="glass-card p-8 space-y-6">
          <div className="space-y-2">
            <h2 className="text-2xl font-semibold">Masuk</h2>
            <p className="text-sm text-slate-500">Masukkan kredensial Anda untuk mengakses akun</p>
          </div>

          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            <Input
              label="Alamat Email"
              placeholder="nama@contoh.com"
              leftIcon={<Mail size={18} />}
              error={errors.email?.message}
              {...register('email')}
            />

            <Input
              label="Kata Sandi"
              type="password"
              placeholder="••••••••"
              leftIcon={<Lock size={18} />}
              error={errors.password?.message}
              {...register('password')}
            />

            <Button
              type="submit"
              className="w-full mt-2 h-12"
              isLoading={isLoading}
            >
              Masuk Sekarang <ArrowRight className="ml-2" size={18} />
            </Button>
          </form>

          <div className="text-center pt-4">
            <p className="text-sm text-slate-500">
              Belum punya akun?{' '}
              <Link to="/register" className="text-primary hover:underline font-medium">
                Daftar di sini
              </Link>
            </p>
          </div>
        </div>

        <div className="mt-8 text-center text-xs text-slate-600">
          &copy; 2026 Koperasi ARTHAJAYA. Seluruh hak dilindungi.
        </div>
      </div>
    </div>
  )
}
