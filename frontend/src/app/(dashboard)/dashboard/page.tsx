'use client';

import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import {
  CalendarClock, Users, BedDouble, Receipt, Activity, Pill, FlaskConical, ArrowRight, Clock,
} from 'lucide-react';
import { Card, CardBody, CardHeader, CardTitle, PageHeader, StatCard, Badge, Table, Thead, Tbody, Tr, Th, Td, SkeletonRows, EmptyState } from '@/components/ui/surface';
import { useAuth } from '@/lib/auth/context';
import { appointmentsApi, patientsApi, bedsApi, billingApi } from '@/lib/api/endpoints';
import { formatTime, statusTone } from '@/lib/utils';

export default function DashboardPage() {
  const { user, hasRole } = useAuth();

  const todayISO = new Date().toISOString().split('T')[0];

  const apptsQuery = useQuery({
    queryKey: ['dashboard', 'appointments', 'today'],
    queryFn: () => appointmentsApi.list({ today: true, per_page: 10 }),
  });

  const patientsQuery = useQuery({
    queryKey: ['dashboard', 'patients-count'],
    queryFn: () => patientsApi.list({ per_page: 1 }),
    staleTime: 60_000,
  });

  const bedsQuery = useQuery({
    queryKey: ['dashboard', 'occupancy'],
    queryFn: () => bedsApi.occupancy(),
    enabled: hasRole(['super_admin', 'hospital_admin', 'doctor', 'nurse', 'management']),
  });

  const invoicesQuery = useQuery({
    queryKey: ['dashboard', 'invoices'],
    queryFn: () => billingApi.invoices({ status: 'partially_paid', per_page: 5 }),
    enabled: hasRole(['super_admin', 'hospital_admin', 'cashier', 'management']),
  });

  const greeting = (() => {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
  })();

  const totalPatients = patientsQuery.data?.meta?.total ?? 0;
  const todayAppts    = apptsQuery.data?.data ?? [];
  const occupancy     = bedsQuery.data;

  return (
    <div>
      <PageHeader
        title={`${greeting}, ${user?.name?.split(' ')[0] ?? ''}`}
        description={`Here's what's happening at your facility on ${new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })}.`}
      />

      {/* Stat grid ------------------------------------------------- */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div className="enter enter-1">
          <StatCard
            label="Today's appointments"
            value={todayAppts.length || '—'}
            delta={`${todayAppts.filter((a) => a.status === 'completed').length} completed`}
            icon={<CalendarClock className="h-5 w-5" />}
            tone="info"
          />
        </div>
        <div className="enter enter-2">
          <StatCard
            label="Active patients"
            value={totalPatients.toLocaleString()}
            icon={<Users className="h-5 w-5" />}
            tone="neutral"
          />
        </div>
        <div className="enter enter-3">
          <StatCard
            label="Bed occupancy"
            value={
              occupancy
                ? `${occupancy.occupied ?? 0}/${occupancy.total ?? 0}`
                : '—'
            }
            delta={
              occupancy?.total
                ? `${Math.round(((occupancy.occupied ?? 0) / occupancy.total) * 100)}%`
                : undefined
            }
            icon={<BedDouble className="h-5 w-5" />}
            tone={
              occupancy && occupancy.occupied / (occupancy.total || 1) > 0.85 ? 'warning' : 'neutral'
            }
          />
        </div>
        <div className="enter enter-4">
          <StatCard
            label="Outstanding dues"
            value={
              invoicesQuery.data?.meta?.total
                ? `${invoicesQuery.data.meta.total} invoice(s)`
                : '—'
            }
            icon={<Receipt className="h-5 w-5" />}
            tone="neutral"
          />
        </div>
      </div>

      <div className="grid lg:grid-cols-[2fr_1fr] gap-6">
        {/* Today's appointments ---------------------------- */}
        <Card className="enter enter-3">
          <CardHeader>
            <div>
              <CardTitle>Today's schedule</CardTitle>
              <p className="text-xs text-[var(--color-ink-muted)] mt-0.5">
                Live queue across all providers
              </p>
            </div>
            <Link
              href="/appointments"
              className="text-xs font-medium text-[var(--color-teal-700)] hover:underline flex items-center gap-1"
            >
              View all
              <ArrowRight className="h-3 w-3" />
            </Link>
          </CardHeader>

          <Table>
            <Thead>
              <tr>
                <Th>Time</Th>
                <Th>Patient</Th>
                <Th>Doctor</Th>
                <Th>Status</Th>
                <Th className="text-right">Token</Th>
              </tr>
            </Thead>
            {apptsQuery.isLoading ? (
              <SkeletonRows rows={5} cols={5} />
            ) : todayAppts.length === 0 ? (
              <Tbody>
                <Tr>
                  <Td colSpan={5}>
                    <EmptyState
                      icon={<Clock className="h-5 w-5" />}
                      title="No appointments today"
                      description="Once patients check in, they'll appear here."
                    />
                  </Td>
                </Tr>
              </Tbody>
            ) : (
              <Tbody>
                {todayAppts.map((a) => (
                  <Tr key={a.id}>
                    <Td className="mono text-[13px]">{formatTime(a.scheduled_start)}</Td>
                    <Td>
                      <Link href={`/patients/${a.patient_id}`} className="font-medium hover:underline">
                        {a.patient?.first_name} {a.patient?.last_name}
                      </Link>
                      <div className="text-xs text-[var(--color-ink-muted)] mono">
                        {a.patient?.uhid}
                      </div>
                    </Td>
                    <Td className="text-[13px]">{a.provider?.user?.name ?? '—'}</Td>
                    <Td>
                      <Badge tone={statusTone(a.status)}>
                        {a.status.replace(/_/g, ' ')}
                      </Badge>
                    </Td>
                    <Td className="text-right mono text-[13px]">{a.token_number ?? '—'}</Td>
                  </Tr>
                ))}
              </Tbody>
            )}
          </Table>
        </Card>

        {/* Quick actions ---------------------------------- */}
        <Card className="enter enter-4">
          <CardHeader>
            <CardTitle>Quick actions</CardTitle>
          </CardHeader>
          <CardBody className="space-y-2">
            <QuickAction
              href="/patients/new"
              label="Register new patient"
              icon={<Users className="h-4 w-4" />}
              hint="Create UHID · capture demographics"
            />
            <QuickAction
              href="/appointments/new"
              label="Book appointment"
              icon={<CalendarClock className="h-4 w-4" />}
              hint="Walk-in or scheduled"
            />
            <QuickAction
              href="/pharmacy/dispense"
              label="Dispense medicine"
              icon={<Pill className="h-4 w-4" />}
              hint="FEFO · batch tracking"
            />
            <QuickAction
              href="/laboratory/orders"
              label="Lab orders"
              icon={<FlaskConical className="h-4 w-4" />}
              hint="Pending samples and results"
            />
            <QuickAction
              href="/billing"
              label="Generate invoice"
              icon={<Receipt className="h-4 w-4" />}
              hint="Unified billing · payment collection"
            />
          </CardBody>
        </Card>
      </div>

      {/* Activity ribbon at the bottom */}
      <Card className="mt-6 enter enter-5">
        <CardHeader>
          <CardTitle>Facility pulse</CardTitle>
          <p className="text-xs text-[var(--color-ink-muted)]">Last 7 days</p>
        </CardHeader>
        <CardBody>
          <div className="grid grid-cols-2 md:grid-cols-5 gap-6 text-center">
            <PulseMetric label="New registrations" value="—" />
            <PulseMetric label="Consultations" value="—" />
            <PulseMetric label="Lab tests" value="—" />
            <PulseMetric label="Dispenses" value="—" />
            <PulseMetric label="Revenue" value="—" />
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

function QuickAction({
  href, label, icon, hint,
}: { href: string; label: string; icon: React.ReactNode; hint: string }) {
  return (
    <Link
      href={href as any}
      className="flex items-center gap-3 p-3 rounded-[10px] hover:bg-[var(--color-surface-2)] transition-colors group"
    >
      <div className="h-9 w-9 rounded-[8px] bg-[var(--color-teal-50)] text-[var(--color-teal-600)] grid place-items-center group-hover:bg-[var(--color-teal-100)] transition-colors">
        {icon}
      </div>
      <div className="flex-1 min-w-0">
        <p className="font-medium text-sm">{label}</p>
        <p className="text-xs text-[var(--color-ink-muted)]">{hint}</p>
      </div>
      <ArrowRight className="h-4 w-4 text-[var(--color-ink-muted)] group-hover:text-[var(--color-teal-600)] group-hover:translate-x-0.5 transition-all" />
    </Link>
  );
}

function PulseMetric({ label, value }: { label: string; value: string | number }) {
  return (
    <div>
      <p className="text-[11px] uppercase tracking-widest text-[var(--color-ink-muted)] mb-1.5">
        {label}
      </p>
      <p className="font-display text-2xl number">{value}</p>
    </div>
  );
}
