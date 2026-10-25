'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { CalendarClock, Plus, LogIn, Play, CheckCircle2, XCircle, AlertCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Card, PageHeader, Badge, Table, Thead, Tbody, Tr, Th, Td,
  SkeletonRows, EmptyState,
} from '@/components/ui/surface';
import { appointmentsApi } from '@/lib/api/endpoints';
import { formatTime, formatDate, statusTone } from '@/lib/utils';
import { useAuth } from '@/lib/auth/context';

export default function AppointmentsPage() {
  const qc = useQueryClient();
  const { can } = useAuth();

  const [date, setDate] = useState(new Date().toISOString().split('T')[0]);
  const [status, setStatus] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['appointments', { date, status }],
    queryFn: () => appointmentsApi.list({
      date: date || undefined,
      status: status || undefined,
      per_page: 50,
    } as any),
  });

  const appts = data?.data ?? [];

  const lifecycle = useMutation({
    mutationFn: async ({ id, action }: { id: number; action: 'checkIn' | 'startConsult' | 'complete' | 'cancel' | 'noShow' }) => {
      if (action === 'checkIn')      return appointmentsApi.checkIn(id);
      if (action === 'startConsult') return appointmentsApi.startConsult(id);
      if (action === 'complete')     return appointmentsApi.complete(id);
      if (action === 'cancel')       return appointmentsApi.cancel(id);
      if (action === 'noShow')       return appointmentsApi.markNoShow(id);
      throw new Error('unknown');
    },
    onSuccess: (_, vars) => {
      toast.success(
        {
          checkIn: 'Patient checked in',
          startConsult: 'Consultation started',
          complete: 'Consultation completed',
          cancel: 'Appointment cancelled',
          noShow: 'Marked as no-show',
        }[vars.action],
      );
      qc.invalidateQueries({ queryKey: ['appointments'] });
      qc.invalidateQueries({ queryKey: ['dashboard'] });
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? 'Action failed'),
  });

  return (
    <div>
      <PageHeader
        title="Appointments"
        description="Today's queue, scheduled bookings, and past visits"
        actions={
          can('appointments.create') && (
            <Link href={'/appointments/new' as any}>
              <Button>
                <Plus className="h-4 w-4" />
                New appointment
              </Button>
            </Link>
          )
        }
      />

      {/* Filters */}
      <Card className="mb-5 enter">
        <div className="p-4 flex items-center gap-3 flex-wrap">
          <input
            type="date"
            value={date}
            onChange={(e) => setDate(e.target.value)}
            className="h-10 px-3 rounded-[10px] border border-[var(--color-hairline)] bg-white text-sm focus:outline-none focus:border-[var(--color-teal-500)]"
          />
          <select
            value={status}
            onChange={(e) => setStatus(e.target.value)}
            className="h-10 px-3 rounded-[10px] border border-[var(--color-hairline)] bg-white text-sm focus:outline-none focus:border-[var(--color-teal-500)]"
          >
            <option value="">All statuses</option>
            <option value="booked">Booked</option>
            <option value="confirmed">Confirmed</option>
            <option value="checked_in">Checked in</option>
            <option value="in_consultation">In consultation</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
            <option value="no_show">No show</option>
          </select>
          <p className="ml-auto text-sm text-[var(--color-ink-muted)]">
            {appts.length} appointment{appts.length === 1 ? '' : 's'}
          </p>
        </div>
      </Card>

      {/* List */}
      <Card className="enter enter-2">
        <Table>
          <Thead>
            <tr>
              <Th>Time</Th>
              <Th>Patient</Th>
              <Th>Doctor</Th>
              <Th>Complaint</Th>
              <Th>Status</Th>
              <Th className="text-right">Actions</Th>
            </tr>
          </Thead>
          {isLoading ? (
            <SkeletonRows rows={6} cols={6} />
          ) : appts.length === 0 ? (
            <Tbody>
              <Tr>
                <Td colSpan={6}>
                  <EmptyState
                    icon={<CalendarClock className="h-5 w-5" />}
                    title={`No appointments on ${formatDate(date)}`}
                    description="Adjust the date filter or book a new appointment."
                  />
                </Td>
              </Tr>
            </Tbody>
          ) : (
            <Tbody>
              {appts.map((a) => (
                <Tr key={a.id}>
                  <Td>
                    <div className="mono text-[13px]">{formatTime(a.scheduled_start)}</div>
                    <div className="text-xs text-[var(--color-ink-muted)] mono">{a.token_number ?? '—'}</div>
                  </Td>
                  <Td>
                    <Link href={`/patients/${a.patient_id}` as any} className="font-medium hover:underline">
                      {a.patient?.first_name} {a.patient?.last_name}
                    </Link>
                    <div className="text-xs text-[var(--color-ink-muted)] mono">{a.patient?.uhid}</div>
                  </Td>
                  <Td className="text-[13px]">{a.provider?.user?.name ?? '—'}</Td>
                  <Td className="text-[13px] max-w-[260px] truncate">{a.chief_complaint ?? '—'}</Td>
                  <Td><Badge tone={statusTone(a.status)}>{a.status.replace(/_/g, ' ')}</Badge></Td>
                  <Td>
                    <div className="flex items-center gap-1 justify-end">
                      {a.status === 'booked' || a.status === 'confirmed' ? (
                        <Button size="xs" onClick={() => lifecycle.mutate({ id: a.id, action: 'checkIn' })}>
                          <LogIn className="h-3 w-3" /> Check in
                        </Button>
                      ) : a.status === 'checked_in' ? (
                        <Button size="xs" onClick={() => lifecycle.mutate({ id: a.id, action: 'startConsult' })}>
                          <Play className="h-3 w-3" /> Start
                        </Button>
                      ) : a.status === 'in_consultation' ? (
                        <Button size="xs" variant="outline" onClick={() => lifecycle.mutate({ id: a.id, action: 'complete' })}>
                          <CheckCircle2 className="h-3 w-3" /> Complete
                        </Button>
                      ) : null}

                      {['booked', 'confirmed', 'checked_in'].includes(a.status) && (
                        <Button size="xs" variant="ghost" onClick={() => lifecycle.mutate({ id: a.id, action: 'cancel' })}>
                          <XCircle className="h-3 w-3" />
                        </Button>
                      )}
                      {['booked', 'confirmed'].includes(a.status) && (
                        <Button size="xs" variant="ghost" onClick={() => lifecycle.mutate({ id: a.id, action: 'noShow' })} title="Mark no-show">
                          <AlertCircle className="h-3 w-3" />
                        </Button>
                      )}
                    </div>
                  </Td>
                </Tr>
              ))}
            </Tbody>
          )}
        </Table>
      </Card>
    </div>
  );
}
