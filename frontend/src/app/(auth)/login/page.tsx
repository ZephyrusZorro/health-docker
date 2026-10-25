'use client';

import { useState, type FormEvent } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Eye, EyeOff } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Field, Input } from '@/components/ui/field';
import { useAuth } from '@/lib/auth/context';

const DEMO_ACCOUNTS = [
  { role: 'Super Admin',    email: 'super@nishat.local' },
  { role: 'Hospital Admin', email: 'admin@nishat.local' },
  { role: 'Receptionist',   email: 'reception@nishat.local' },
  { role: 'Doctor',         email: 'arun@nishat.local' },
  { role: 'Nurse',          email: 'lakshmi@nishat.local' },
  { role: 'Pharmacist',     email: 'pharmacy@nishat.local' },
  { role: 'Lab Technician', email: 'lab@nishat.local' },
  { role: 'Cashier',        email: 'cashier@nishat.local' },
  { role: 'Management',     email: 'management@nishat.local' },
];

export default function LoginPage() {
  const { login } = useAuth();
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPw, setShowPw] = useState(false);
  const [loading, setLoading] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setLoading(true);
    try {
      await login(email, password);
      toast.success('Welcome back!');
      router.replace('/dashboard');
    } catch (err: any) {
      const msg = err.response?.data?.errors?.email?.[0]
        ?? err.response?.data?.message
        ?? 'Invalid email or password';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  }

  function fillDemo(email: string) {
    setEmail(email);
    setPassword('password');
  }

  return (
    <div className="enter">
      <div className="mb-8 lg:hidden">
        <div className="flex items-center gap-3">
          <svg viewBox="0 0 32 32" className="h-8 w-8 text-[var(--color-teal-600)]" aria-hidden>
            <circle cx="16" cy="16" r="15" fill="none" stroke="currentColor" strokeWidth="1.5" />
            <path d="M16 8v16M8 16h16" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" />
          </svg>
          <span className="font-display text-xl">Nishat HMS</span>
        </div>
      </div>

      <h2 className="font-display text-[32px] leading-none tracking-tight mb-2">Sign in</h2>
      <p className="text-sm text-[var(--color-ink-muted)] mb-8">
        Enter your staff credentials to continue.
      </p>

      <form onSubmit={onSubmit} className="space-y-4">
        <Field label="Email">
          <Input
            type="email"
            autoComplete="email"
            placeholder="you@hospital.local"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
          />
        </Field>

        <Field label="Password">
          <div className="relative">
            <Input
              type={showPw ? 'text' : 'password'}
              autoComplete="current-password"
              placeholder="••••••••"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="pr-11"
            />
            <button
              type="button"
              onClick={() => setShowPw((v) => !v)}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--color-ink-muted)] hover:text-[var(--color-ink)]"
              aria-label={showPw ? 'Hide password' : 'Show password'}
            >
              {showPw ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </button>
          </div>
        </Field>

        <Button type="submit" loading={loading} size="lg" className="w-full mt-2">
          Sign in
        </Button>
      </form>

      <div className="mt-10 pt-8 border-t border-[var(--color-hairline)]">
        <p className="text-xs uppercase tracking-widest text-[var(--color-ink-muted)] mb-3 font-medium">
          Demo accounts · password <code className="px-1 py-0.5 bg-[var(--color-surface-2)] rounded text-[10px]">password</code>
        </p>
        <div className="grid grid-cols-2 gap-1.5">
          {DEMO_ACCOUNTS.map((a) => (
            <button
              key={a.email}
              type="button"
              onClick={() => fillDemo(a.email)}
              className="text-left text-xs py-1.5 px-2.5 rounded-md hover:bg-[var(--color-teal-50)] hover:text-[var(--color-teal-700)] transition-colors"
            >
              <span className="block font-medium">{a.role}</span>
              <span className="block text-[var(--color-ink-muted)] truncate">{a.email}</span>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}
