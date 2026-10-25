'use client';

import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { publicApi } from '@/lib/api/endpoints';
import { initials } from '@/lib/utils';
import { Stethoscope, Calendar } from 'lucide-react';

export default function PublicDoctorsPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['public', 'doctors'],
    queryFn: () => publicApi.doctors(),
  });

  const doctors = data ?? [];

  return (
    <div className="max-w-6xl mx-auto px-6 py-16">
      <div className="mb-12 text-center">
        <p className="text-xs uppercase tracking-widest text-[var(--color-teal-600)] font-medium mb-2">
          Our team
        </p>
        <h1 className="font-display text-5xl tracking-tight mb-3">Meet our doctors</h1>
        <p className="text-[var(--color-ink-muted)] max-w-lg mx-auto">
          Browse our specialists, their qualifications, and book an appointment directly.
        </p>
      </div>

      {isLoading ? (
        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="skeleton h-64" />
          ))}
        </div>
      ) : doctors.length === 0 ? (
        <div className="card p-16 text-center">
          <Stethoscope className="h-10 w-10 mx-auto text-[var(--color-ink-muted)] mb-3" />
          <p className="text-[var(--color-ink-muted)]">No doctors available at the moment.</p>
        </div>
      ) : (
        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
          {doctors.map((d) => (
            <div key={d.id} className="card card-hover p-6">
              <div className="flex items-start gap-4 mb-4">
                <div className="h-16 w-16 rounded-full bg-[var(--color-teal-50)] text-[var(--color-teal-700)] grid place-items-center font-display text-2xl shrink-0">
                  {initials(d.user?.name)}
                </div>
                <div className="flex-1 min-w-0">
                  <h3 className="font-display text-xl leading-tight mb-0.5">{d.user?.name}</h3>
                  <p className="text-xs uppercase tracking-widest text-[var(--color-teal-700)] font-medium">
                    {d.specialization ?? d.department?.name}
                  </p>
                  <p className="text-xs text-[var(--color-ink-muted)] mt-1">{d.qualification}</p>
                </div>
              </div>

              {d.bio && (
                <p className="text-sm text-[var(--color-ink-muted)] leading-relaxed mb-4 line-clamp-3">
                  {d.bio}
                </p>
              )}

              <div className="flex items-center justify-between pt-4 border-t border-[var(--color-hairline)]">
                <div className="text-sm">
                  <p className="text-xs uppercase tracking-widest text-[var(--color-ink-muted)]">Fee</p>
                  <p className="mono font-medium">₹{d.consultation_fee ?? '—'}</p>
                </div>
                <Link
                  href={`/welcome/book?provider=${d.id}`}
                  className="inline-flex items-center gap-1.5 text-sm font-medium text-[var(--color-teal-700)] hover:text-[var(--color-teal-900)]"
                >
                  <Calendar className="h-4 w-4" />
                  Book
                </Link>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
