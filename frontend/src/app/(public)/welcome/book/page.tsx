'use client';

import { useSearchParams } from 'next/navigation';
import { useEffect, useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Calendar, CheckCircle2 } from 'lucide-react';
import { publicApi } from '@/lib/api/endpoints';
import { formatTime, initials } from '@/lib/utils';
import { cn } from '@/lib/utils';

export default function PublicBookPage() {
  const sp = useSearchParams();
  const prefillProvider = sp.get('provider');

  const [providerId, setProviderId] = useState<number | null>(prefillProvider ? Number(prefillProvider) : null);
  const [date, setDate] = useState(new Date().toISOString().split('T')[0]);
  const [slotId, setSlotId] = useState<number | null>(null);
  const [confirmation, setConfirmation] = useState<any | null>(null);

  const [form, setForm] = useState({
    first_name: '', last_name: '', phone: '', email: '',
    date_of_birth: '', gender: 'male',
    chief_complaint: '',
  });

  const doctorsQ = useQuery({ queryKey: ['public', 'doctors'], queryFn: () => publicApi.doctors() });
  const slotsQ = useQuery({
    queryKey: ['public', 'slots', providerId, date],
    queryFn: () => publicApi.availability(providerId!, date),
    enabled: !!providerId,
  });

  const book = useMutation({
    mutationFn: () =>
      publicApi.bookAppointment({
        ...form,
        slot_id: slotId,
      }),
    onSuccess: (appt) => {
      setConfirmation(appt);
      toast.success('Appointment booked!');
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? 'Booking failed'),
  });

  const chosenDoctor = doctorsQ.data?.find((d: any) => d.id === providerId);

  if (confirmation) {
    return (
      <div className="max-w-2xl mx-auto px-6 py-24">
        <div className="card p-10 text-center">
          <div className="h-14 w-14 rounded-full bg-emerald-50 text-emerald-600 grid place-items-center mx-auto mb-5">
            <CheckCircle2 className="h-7 w-7" />
          </div>
          <h1 className="font-display text-3xl tracking-tight mb-2">You're all set!</h1>
          <p className="text-[var(--color-ink-muted)] mb-8">
            We've sent a confirmation to <span className="mono">{form.phone}</span>.
          </p>

          <div className="bg-[var(--color-surface-2)] rounded-[14px] p-6 text-left space-y-3 mb-8">
            <div className="flex justify-between text-sm">
              <span className="text-[var(--color-ink-muted)]">Appointment #</span>
              <span className="mono font-medium">{confirmation.appointment_number}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-[var(--color-ink-muted)]">Token</span>
              <span className="mono font-medium">{confirmation.token_number}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-[var(--color-ink-muted)]">Doctor</span>
              <span>{confirmation.provider?.user?.name}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-[var(--color-ink-muted)]">Date & time</span>
              <span>{new Date(confirmation.scheduled_start).toLocaleString()}</span>
            </div>
          </div>

          <p className="text-sm text-[var(--color-ink-muted)]">
            Please arrive 10 minutes before your appointment. Bring ID and any prior records.
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-5xl mx-auto px-6 py-16">
      <div className="text-center mb-10">
        <h1 className="font-display text-5xl tracking-tight mb-2">Book an appointment</h1>
        <p className="text-[var(--color-ink-muted)]">Takes less than a minute.</p>
      </div>

      <div className="grid md:grid-cols-[1fr_360px] gap-6">
        {/* Left: form */}
        <div className="space-y-5">
          {/* Step 1 — doctor */}
          <div className="card p-6">
            <p className="text-xs uppercase tracking-widest text-[var(--color-teal-600)] font-medium mb-3">Step 1 · Choose a doctor</p>
            <div className="grid gap-2">
              {(doctorsQ.data ?? []).map((d: any) => (
                <button
                  key={d.id}
                  type="button"
                  onClick={() => { setProviderId(d.id); setSlotId(null); }}
                  className={cn(
                    'flex items-center gap-3 p-3 rounded-[10px] border text-left transition-all',
                    providerId === d.id
                      ? 'border-[var(--color-teal-500)] bg-[var(--color-teal-50)]'
                      : 'border-[var(--color-hairline)] hover:bg-[var(--color-surface-2)]',
                  )}
                >
                  <div className="h-10 w-10 rounded-full bg-[var(--color-teal-100)] text-[var(--color-teal-700)] grid place-items-center text-xs font-medium">
                    {initials(d.user?.name)}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-sm">{d.user?.name}</p>
                    <p className="text-xs text-[var(--color-ink-muted)] truncate">{d.specialization} · ₹{d.consultation_fee}</p>
                  </div>
                </button>
              ))}
            </div>
          </div>

          {/* Step 2 — slot */}
          {providerId && (
            <div className="card p-6">
              <p className="text-xs uppercase tracking-widest text-[var(--color-teal-600)] font-medium mb-3">Step 2 · Pick a time</p>
              <input
                type="date"
                value={date}
                min={new Date().toISOString().split('T')[0]}
                onChange={(e) => { setDate(e.target.value); setSlotId(null); }}
                className="h-10 px-3 rounded-[10px] border border-[var(--color-hairline)] bg-white text-sm mb-4 focus:outline-none focus:border-[var(--color-teal-500)]"
              />
              {slotsQ.isLoading ? (
                <div className="grid grid-cols-4 gap-2">
                  {Array.from({ length: 8 }).map((_, i) => <div key={i} className="skeleton h-10" />)}
                </div>
              ) : (slotsQ.data?.length ?? 0) === 0 ? (
                <p className="text-sm text-[var(--color-ink-muted)]">No slots available on this day.</p>
              ) : (
                <div className="grid grid-cols-3 sm:grid-cols-4 gap-2">
                  {slotsQ.data!.map((s: any) => (
                    <button
                      key={s.id}
                      type="button"
                      disabled={!s.available}
                      onClick={() => setSlotId(s.id)}
                      className={cn(
                        'h-10 rounded-[10px] border text-sm font-medium transition-all mono',
                        slotId === s.id
                          ? 'bg-[var(--color-teal-600)] border-[var(--color-teal-600)] text-white'
                          : s.available
                          ? 'border-[var(--color-hairline)] hover:border-[var(--color-teal-500)] hover:bg-[var(--color-teal-50)]'
                          : 'border-[var(--color-hairline)] bg-[var(--color-surface-2)] opacity-40 cursor-not-allowed',
                      )}
                    >
                      {s.start_time.slice(0, 5)}
                    </button>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* Step 3 — details */}
          {slotId && (
            <div className="card p-6">
              <p className="text-xs uppercase tracking-widest text-[var(--color-teal-600)] font-medium mb-4">Step 3 · Your details</p>
              <div className="grid sm:grid-cols-2 gap-3">
                <input placeholder="First name *" required value={form.first_name} onChange={(e) => setForm({ ...form, first_name: e.target.value })} className="h-10 px-3 rounded-[10px] border border-[var(--color-hairline)] bg-white text-sm focus:outline-none focus:border-[var(--color-teal-500)]" />
                <input placeholder="Last name" value={form.last_name} onChange={(e) => setForm({ ...form, last_name: e.target.value })} className="h-10 px-3 rounded-[10px] border border-[var(--color-hairline)] bg-white text-sm focus:outline-none focus:border-[var(--color-teal-500)]" />
                <input placeholder="Phone *" required value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} className="h-10 px-3 rounded-[10px] border border-[var(--color-hairline)] bg-white text-sm focus:outline-none focus:border-[var(--color-teal-500)]" />
                <input type="email" placeholder="Email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} className="h-10 px-3 rounded-[10px] border border-[var(--color-hairline)] bg-white text-sm focus:outline-none focus:border-[var(--color-teal-500)]" />
                <input type="date" placeholder="Date of birth *" required value={form.date_of_birth} onChange={(e) => setForm({ ...form, date_of_birth: e.target.value })} className="h-10 px-3 rounded-[10px] border border-[var(--color-hairline)] bg-white text-sm focus:outline-none focus:border-[var(--color-teal-500)]" />
                <select value={form.gender} onChange={(e) => setForm({ ...form, gender: e.target.value })} className="h-10 px-3 rounded-[10px] border border-[var(--color-hairline)] bg-white text-sm focus:outline-none focus:border-[var(--color-teal-500)]">
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                </select>
                <textarea
                  placeholder="Describe your reason for visit (optional)"
                  value={form.chief_complaint}
                  onChange={(e) => setForm({ ...form, chief_complaint: e.target.value })}
                  rows={3}
                  className="sm:col-span-2 px-3 py-2 rounded-[10px] border border-[var(--color-hairline)] bg-white text-sm focus:outline-none focus:border-[var(--color-teal-500)]"
                />
              </div>

              <button
                onClick={() => book.mutate()}
                disabled={book.isPending || !form.first_name || !form.phone || !form.date_of_birth}
                className="mt-5 w-full h-12 bg-[var(--color-teal-600)] hover:bg-[var(--color-teal-700)] text-white rounded-[10px] font-medium disabled:opacity-50 transition-colors flex items-center justify-center gap-2"
              >
                {book.isPending ? 'Booking…' : (
                  <>
                    <Calendar className="h-4 w-4" />
                    Confirm booking
                  </>
                )}
              </button>
            </div>
          )}
        </div>

        {/* Right: summary */}
        <aside className="md:sticky md:top-24 md:self-start">
          <div className="card p-6">
            <p className="text-xs uppercase tracking-widest text-[var(--color-ink-muted)] font-medium mb-3">Your booking</p>
            {chosenDoctor ? (
              <div className="space-y-3 text-sm">
                <Row label="Doctor" value={chosenDoctor.user?.name} />
                <Row label="Specialty" value={chosenDoctor.specialization ?? '—'} />
                <Row label="Date" value={new Date(date).toLocaleDateString()} />
                <Row label="Time" value={slotId && slotsQ.data ? slotsQ.data.find((s: any) => s.id === slotId)?.start_time.slice(0, 5) : '—'} />
                <div className="border-t border-[var(--color-hairline)] pt-3 mt-3 flex justify-between font-medium">
                  <span>Consultation fee</span>
                  <span className="mono">₹{chosenDoctor.consultation_fee}</span>
                </div>
              </div>
            ) : (
              <p className="text-sm text-[var(--color-ink-muted)]">Choose a doctor to get started.</p>
            )}
          </div>
        </aside>
      </div>
    </div>
  );
}

function Row({ label, value }: { label: string; value: any }) {
  return (
    <div className="flex justify-between">
      <span className="text-[var(--color-ink-muted)]">{label}</span>
      <span className="font-medium text-right">{value ?? '—'}</span>
    </div>
  );
}
