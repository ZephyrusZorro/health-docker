'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  LayoutDashboard, Users, CalendarClock, Stethoscope, Pill, FlaskConical,
  Receipt, BedDouble, Shield, ClipboardList, LucideIcon, LogOut,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { useAuth } from '@/lib/auth/context';

type NavItem = {
  label: string;
  href: string;
  icon: LucideIcon;
  perm?: string;          // permission required to see the link
  roles?: string[];       // OR restrict to these roles
};

const NAV: Array<{ section: string; items: NavItem[] }> = [
  {
    section: 'Workspace',
    items: [
      { label: 'Dashboard',     href: '/dashboard',     icon: LayoutDashboard },
      { label: 'Patients',      href: '/patients',      icon: Users,          perm: 'patients.view' },
      { label: 'Appointments',  href: '/appointments',  icon: CalendarClock,  perm: 'appointments.view' },
      { label: 'Encounters',    href: '/encounters',    icon: Stethoscope,    perm: 'emr.view' },
    ],
  },
  {
    section: 'Operations',
    items: [
      { label: 'Pharmacy',      href: '/pharmacy',      icon: Pill,           perm: 'pharmacy.view' },
      { label: 'Laboratory',    href: '/laboratory',    icon: FlaskConical,   perm: 'lab.view' },
      { label: 'Billing',       href: '/billing',       icon: Receipt,        perm: 'billing.view' },
      { label: 'Wards & Beds',  href: '/wards',         icon: BedDouble,      perm: 'ipd.view' },
    ],
  },
  {
    section: 'System',
    items: [
      { label: 'Administration',href: '/admin',         icon: Shield,         perm: 'admin.access' },
      { label: 'Audit Logs',    href: '/admin/audit',   icon: ClipboardList,  perm: 'admin.access' },
    ],
  },
];

export function Sidebar() {
  const pathname = usePathname();
  const { user, can, hasRole, logout } = useAuth();

  return (
    <aside className="w-[244px] shrink-0 border-r border-[var(--color-hairline)] bg-white flex flex-col">
      {/* Brand */}
      <div className="h-16 px-5 flex items-center gap-2.5 border-b border-[var(--color-hairline)]">
        <svg viewBox="0 0 32 32" className="h-7 w-7 text-[var(--color-teal-600)]">
          <circle cx="16" cy="16" r="15" fill="none" stroke="currentColor" strokeWidth="1.5" />
          <path d="M16 8v16M8 16h16" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" />
        </svg>
        <span className="font-display text-lg tracking-tight">Nishat HMS</span>
      </div>

      {/* Nav */}
      <nav className="flex-1 overflow-y-auto py-4 px-2.5">
        {NAV.map((section) => {
          const visible = section.items.filter(
            (i) => !i.perm || can(i.perm) || (i.roles && hasRole(i.roles)),
          );
          if (visible.length === 0) return null;
          return (
            <div key={section.section} className="mb-6">
              <p className="px-3 mb-1.5 text-[10px] font-semibold uppercase tracking-widest text-[var(--color-ink-muted)]">
                {section.section}
              </p>
              {visible.map((item) => {
                const active = pathname === item.href || pathname.startsWith(`${item.href}/`);
                return (
                  <Link
                    key={item.href}
                    href={item.href as any}
                    className={cn(
                      'flex items-center gap-3 px-3 py-2 rounded-[10px] text-sm mb-0.5 transition-all',
                      active
                        ? 'bg-[var(--color-teal-50)] text-[var(--color-teal-700)] font-medium'
                        : 'text-[var(--color-ink)] hover:bg-[var(--color-surface-2)]',
                    )}
                  >
                    <item.icon className="h-[17px] w-[17px] shrink-0" strokeWidth={active ? 2.2 : 1.8} />
                    {item.label}
                    {active && (
                      <span className="ml-auto h-1.5 w-1.5 rounded-full bg-[var(--color-teal-600)]" />
                    )}
                  </Link>
                );
              })}
            </div>
          );
        })}
      </nav>

      {/* User card */}
      <div className="p-3 border-t border-[var(--color-hairline)]">
        <div className="flex items-center gap-3 px-2 py-1.5">
          <div className="h-9 w-9 rounded-full bg-[var(--color-teal-100)] text-[var(--color-teal-700)] grid place-items-center font-medium text-sm">
            {user?.name?.slice(0, 2).toUpperCase() ?? '??'}
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium truncate">{user?.name}</p>
            <p className="text-xs text-[var(--color-ink-muted)] truncate capitalize">
              {user?.roles?.[0]?.display_name ?? user?.roles?.[0]?.name?.replace(/_/g, ' ')}
            </p>
          </div>
          <button
            onClick={logout}
            className="p-2 rounded-md text-[var(--color-ink-muted)] hover:text-[var(--color-rose-500)] hover:bg-[var(--color-surface-2)] transition-colors"
            title="Sign out"
            aria-label="Sign out"
          >
            <LogOut className="h-4 w-4" />
          </button>
        </div>
      </div>
    </aside>
  );
}
