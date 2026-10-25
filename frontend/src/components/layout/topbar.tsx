'use client';

import { useRouter } from 'next/navigation';
import { Search, Bell } from 'lucide-react';
import { useState } from 'react';
import { useAuth } from '@/lib/auth/context';

export function Topbar() {
  const { user } = useAuth();
  const router = useRouter();
  const [q, setQ] = useState('');

  function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (q.trim()) router.push(`/patients?q=${encodeURIComponent(q.trim())}`);
  }

  return (
    <header className="h-16 border-b border-[var(--color-hairline)] bg-white/70 backdrop-blur-sm sticky top-0 z-20 flex items-center px-6 gap-4">
      {/* Global search */}
      <form onSubmit={onSubmit} className="flex-1 max-w-xl">
        <div className="relative">
          <Search className="h-4 w-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--color-ink-muted)]" />
          <input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Search patients by name, UHID, phone…"
            className="w-full h-10 pl-10 pr-4 rounded-[10px] border border-[var(--color-hairline)] bg-[var(--color-surface-2)] text-sm placeholder:text-[var(--color-ink-muted)] focus:outline-none focus:ring-2 focus:ring-[var(--color-teal-100)] focus:border-[var(--color-teal-500)] focus:bg-white transition-all"
          />
          <kbd className="hidden md:inline-flex absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-mono px-1.5 py-0.5 rounded bg-white border border-[var(--color-hairline)] text-[var(--color-ink-muted)]">
            ⌘K
          </kbd>
        </div>
      </form>

      <div className="flex items-center gap-3">
        <button
          className="h-10 w-10 grid place-items-center rounded-[10px] text-[var(--color-ink-muted)] hover:bg-[var(--color-surface-2)] hover:text-[var(--color-ink)] transition-colors relative"
          aria-label="Notifications"
        >
          <Bell className="h-4 w-4" />
          <span className="absolute top-2.5 right-2.5 h-1.5 w-1.5 rounded-full bg-[var(--color-rose-500)]" />
        </button>

        <div className="h-8 w-px bg-[var(--color-hairline)]" />

        <div className="text-right">
          <p className="text-xs text-[var(--color-ink-muted)]">Facility</p>
          <p className="text-sm font-medium">{user?.facility?.name ?? 'Nishat Nursing Home'}</p>
        </div>
      </div>
    </header>
  );
}
