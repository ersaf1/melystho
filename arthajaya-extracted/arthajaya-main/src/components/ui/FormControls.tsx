import React, { forwardRef, useState } from 'react'
import { clsx } from 'clsx'
import { Eye, EyeOff, Loader2 } from 'lucide-react'

// --- Button Component ---
interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost'
  isLoading?: boolean
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant = 'primary', isLoading, children, ...props }, ref) => {
    const variants = {
      primary: 'bg-primary hover:bg-primary-light text-white shadow-lg shadow-primary/20',
      secondary: 'bg-surface-light hover:bg-surface text-white border border-white/10',
      outline: 'bg-transparent border-2 border-primary text-primary hover:bg-primary/10',
      ghost: 'bg-transparent hover:bg-white/5 text-slate-400 hover:text-white',
    }

    return (
      <button
        ref={ref}
        disabled={isLoading}
        className={clsx(
          'inline-flex items-center justify-center rounded-xl px-6 py-2.5 font-semibold transition-all duration-300 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed',
          variants[variant],
          className
        )}
        {...props}
      >
        {isLoading ? <Loader2 className="animate-spin mr-2" size={20} /> : null}
        {children}
      </button>
    )
  }
)

// --- Input Component ---
interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string
  error?: string
  leftIcon?: React.ReactNode
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ label, error, type, className, leftIcon, ...props }, ref) => {
    const [showPassword, setShowPassword] = useState(false)
    const isPassword = type === 'password'

    const togglePassword = (e: React.MouseEvent) => {
      e.preventDefault()
      setShowPassword(!showPassword)
    }

    return (
      <div className="space-y-1.5 w-full">
        {label && (
          <label className="text-xs font-bold uppercase tracking-widest text-slate-500 ml-1">
            {label}
          </label>
        )}
        <div className="relative group">
          {leftIcon && (
            <div className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-primary transition-colors duration-300">
              {leftIcon}
            </div>
          )}
          <input
            ref={ref}
            type={isPassword ? (showPassword ? 'text' : 'password') : type}
            className={clsx(
              'w-full bg-surface-dark border border-white/10 rounded-xl py-2.5 text-white placeholder:text-slate-600 focus:outline-none focus:border-primary/50 focus:ring-1 focus:ring-primary/50 transition-all duration-300',
              leftIcon ? 'pl-11' : 'px-4',
              isPassword ? 'pr-11' : 'pr-4',
              error && 'border-red-500/50 focus:border-red-500 focus:ring-red-500',
              className
            )}
            {...props}
          />
          {isPassword && (
            <button
              type="button"
              onClick={togglePassword}
              className="absolute right-3.5 top-1/2 -translate-y-1/2 p-1 text-slate-500 hover:text-primary transition-colors focus:outline-none"
              tabIndex={-1}
            >
              {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
            </button>
          )}
        </div>
        {error && (
          <p className="text-xs text-red-500 mt-1 ml-1 animate-in fade-in slide-in-from-top-1">
            {error}
          </p>
        )}
      </div>
    )
  }
)
// --- Select Component ---
interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> {
  label?: string
  error?: string
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(
  ({ label, error, className, children, ...props }, ref) => {
    return (
      <div className="space-y-1.5 w-full">
        {label && (
          <label className="text-xs font-bold uppercase tracking-widest text-slate-500 ml-1">
            {label}
          </label>
        )}
        <select
          ref={ref}
          className={clsx(
            'w-full bg-surface-dark border border-white/10 rounded-xl py-2.5 px-4 text-white focus:outline-none focus:border-primary/50 focus:ring-1 focus:ring-primary/50 transition-all duration-300 appearance-none cursor-pointer',
            error && 'border-red-500/50 focus:border-red-500 focus:ring-red-500',
            className
          )}
          {...props}
        >
          {children}
        </select>
        {error && (
          <p className="text-xs text-red-500 mt-1 ml-1 animate-in fade-in slide-in-from-top-1">
            {error}
          </p>
        )}
      </div>
    )
  }
)

// --- Textarea Component ---
interface TextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: string
  error?: string
}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(
  ({ label, error, className, ...props }, ref) => {
    return (
      <div className="space-y-1.5 w-full">
        {label && (
          <label className="text-xs font-bold uppercase tracking-widest text-slate-500 ml-1">
            {label}
          </label>
        )}
        <textarea
          ref={ref}
          className={clsx(
            'w-full bg-surface-dark border border-white/10 rounded-xl py-2.5 px-4 text-white placeholder:text-slate-600 focus:outline-none focus:border-primary/50 focus:ring-1 focus:ring-primary/50 transition-all duration-300 min-h-[100px] resize-none',
            error && 'border-red-500/50 focus:border-red-500 focus:ring-red-500',
            className
          )}
          {...props}
        />
        {error && (
          <p className="text-xs text-red-500 mt-1 ml-1 animate-in fade-in slide-in-from-top-1">
            {error}
          </p>
        )}
      </div>
    )
  }
)
