import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

/** Merge Tailwind classes conditionally — used by every component. */
export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

/** Format an ISO date for reading: "Apr 22, 2026, 10:15 AM". */
export function formatDateTime(iso: string | Date | null | undefined) {
  if (!iso) return '—';
  const d = typeof iso === 'string' ? new Date(iso) : iso;
  if (Number.isNaN(d.getTime())) return '—';
  return d.toLocaleString(undefined, {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: 'numeric', minute: '2-digit',
  });
}

export function formatDate(iso: string | Date | null | undefined) {
  if (!iso) return '—';
  const d = typeof iso === 'string' ? new Date(iso) : iso;
  return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export function formatTime(iso: string | Date | null | undefined) {
  if (!iso) return '—';
  const d = typeof iso === 'string' ? new Date(iso) : iso;
  return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
}

export function formatMoney(amount: number | string | null | undefined, currency = 'INR') {
  if (amount === null || amount === undefined) return '—';
  const n = typeof amount === 'string' ? parseFloat(amount) : amount;
  if (Number.isNaN(n)) return '—';
  return new Intl.NumberFormat('en-IN', {
    style: 'currency', currency, maximumFractionDigits: 2,
  }).format(n);
}

/** Derive initials from a name for avatars. */
export function initials(name?: string | null): string {
  if (!name) return '??';
  const parts = name.trim().split(/\s+/).slice(0, 2);
  return parts.map((p) => p[0]?.toUpperCase() ?? '').join('');
}

/** Compute age from DOB. */
export function age(dob: string | null | undefined): number | null {
  if (!dob) return null;
  const d = new Date(dob);
  if (Number.isNaN(d.getTime())) return null;
  const now = new Date();
  let a = now.getFullYear() - d.getFullYear();
  const m = now.getMonth() - d.getMonth();
  if (m < 0 || (m === 0 && now.getDate() < d.getDate())) a--;
  return a;
}

/** Status → color mapping used across badges. */
export function statusTone(
  status: string,
): 'neutral' | 'info' | 'success' | 'warning' | 'danger' {
  const s = status?.toLowerCase();
  if (!s) return 'neutral';
  if (['completed', 'verified', 'paid', 'discharged', 'sent', 'dispensed', 'available'].includes(s)) return 'success';
  if (['booked', 'confirmed', 'checked_in', 'in_consultation', 'active', 'ordered', 'sample_collected'].includes(s)) return 'info';
  if (['draft', 'pending', 'queued', 'cleaning'].includes(s)) return 'neutral';
  if (['cancelled', 'no_show', 'failed', 'refunded', 'expired'].includes(s)) return 'danger';
  if (['abnormal', 'low_stock', 'expiring'].includes(s)) return 'warning';
  return 'neutral';
}
