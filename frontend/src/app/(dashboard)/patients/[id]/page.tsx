'use client';

import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import Link from 'next/link';
import {
  ArrowLeft, User, Heart, Activity, Pill, FlaskConical, FileText,
  Phone, Mail, MapPin, AlertTriangle,
} from 'lucide-react';
import {
  Card, CardBody, CardHeader, CardTitle, PageHeader, Badge, Table,
  Thead, Tbody, Tr, Th, Td, EmptyState,
} from '@/components/ui/surface';
import { Button } from '@/components/ui/button';
import { patientsApi, encountersApi, prescriptionsApi } from '@/lib/api/endpoints';
import { age, formatDate, formatDateTime, initials, statusTone } from '@/lib/utils';
import { cn } from '@/lib/utils';

type Tab = 'overview' | 'timeline' | 'vitals' | 'prescriptions' | 'documents';

const TABS: Array<{ id: Tab; label: string; icon: React.ComponentType<{ className?: string }> }> = [
  { id: 'overview',      label: 'Overview',       icon: User },
  { id: 'timeline',      label: 'Visit timeline', icon: Activity },
  { id: 'vitals',        label: 'Vitals',         icon: Heart },
  { id: 'prescriptions', label: 'Prescriptions',  icon: Pill },
  { id: 'documents',     label: 'Documents',      icon: FileText },
];

export default function PatientDetailPage() {
  const { id } = useParams<{ id: string }>();
  const patientId = Number(id);
  const [tab, setTab] = useState<Tab>('overview');

  const { data: patient, isLoading } = useQuery({
    queryKey: ['patient', patientId],
    queryFn: () => patientsApi.get(patientId),
    enabled: !!patientId,
  });

  const { data: vitals } = useQuery({
    queryKey: ['patient', patientId, 'observations'],
    queryFn: () => patientsApi.observations(patientId),
    enabled: !!patientId && (tab === 'vitals' || tab === 'overview'),
  });

  const encountersQuery = useQuery({
    queryKey: ['patient', patientId, 'encounters'],
    queryFn: () => encountersApi.list({ patient_id: patientId, per_page: 50 } as any),
    enabled: !!patientId && (tab === 'timeline' || tab === 'overview'),
  });

  const rxQuery = useQuery({
    queryKey: ['patient', patientId, 'prescriptions'],
    queryFn: () => prescriptionsApi.list({ patient_id: patientId, per_page: 50 } as any),
    enabled: !!patientId && tab === 'prescriptions',
  });

  if (isLoading || !patient) {
    return (
      <div className="space-y-4">
        <div className="skeleton h-8 w-48" />
        <div className="skeleton h-32 w-full" />
        <div className="skeleton h-96 w-full" />
      </div>
    );
  }

  // Most-recent vitals for summary card
  const latestBP = getLatestVital(vitals ?? [], '8480-6');
  const latestHR = getLatestVital(vitals ?? [], '8867-4');
  const latestSpO2 = getLatestVital(vitals ?? [], '59408-5');
  const latestTemp = getLatestVital(vitals ?? [], '8310-5');

  return (
    <div>
      <Link href="/patients" className="text-sm text-[var(--color-ink-muted)] hover:text-[var(--color-ink)] inline-flex items-center gap-1 mb-3">
        <ArrowLeft className="h-3.5 w-3.5" />
        Back to patients
      </Link>

      {/* Patient header card */}
      <Card className="mb-6 enter">
        <CardBody className="flex items-start gap-6 flex-wrap">
          <div className="h-16 w-16 rounded-full bg-[var(--color-teal-50)] text-[var(--color-teal-700)] grid place-items-center font-display text-2xl shrink-0">
            {initials(`${patient.first_name} ${patient.last_name ?? ''}`)}
          </div>

          <div className="flex-1 min-w-[240px]">
            <div className="flex items-start gap-3 flex-wrap">
              <h1 className="font-display text-3xl leading-none tracking-tight">
                {patient.first_name} {patient.last_name}
              </h1>
              {patient.is_vip && <Badge tone="warning">VIP</Badge>}
              {patient.is_deceased && <Badge tone="danger">Deceased</Badge>}
            </div>

            <p className="mt-2 flex items-center gap-3 flex-wrap text-sm text-[var(--color-ink-muted)]">
              <span className="mono text-[var(--color-teal-700)] font-medium">{patient.uhid}</span>
              <Dot />
              <span>{age(patient.date_of_birth)} yrs</span>
              <Dot />
              <span className="capitalize">{patient.gender}</span>
              {patient.blood_group && (<><Dot /><span>Blood <span className="mono">{patient.blood_group}</span></span></>)}
              <Dot />
              <Badge tone="info" className="capitalize">{patient.payer_type}</Badge>
            </p>

            <div className="grid sm:grid-cols-3 gap-4 mt-5">
              {patient.phone && (
                <InfoLine icon={<Phone className="h-3.5 w-3.5" />} label="Phone" value={patient.phone} mono />
              )}
              {patient.email && (
                <InfoLine icon={<Mail className="h-3.5 w-3.5" />} label="Email" value={patient.email} />
              )}
              {patient.address && (
                <InfoLine
                  icon={<MapPin className="h-3.5 w-3.5" />}
                  label="Address"
                  value={[patient.address.line1, patient.address.city].filter(Boolean).join(', ')}
                />
              )}
            </div>
          </div>

          <div className="flex flex-col gap-2">
            <Link href={`/appointments/new?patient_id=${patient.id}` as any}>
              <Button size="sm">Book appointment</Button>
            </Link>
            <Link href={`/encounters/new?patient_id=${patient.id}` as any}>
              <Button variant="secondary" size="sm">Start encounter</Button>
            </Link>
          </div>
        </CardBody>
      </Card>

      {/* Tabs */}
      <div className="flex items-center gap-1 mb-5 overflow-x-auto pb-1">
        {TABS.map((t) => (
          <button
            key={t.id}
            onClick={() => setTab(t.id)}
            className={cn(
              'flex items-center gap-2 px-4 h-10 rounded-[10px] text-sm font-medium whitespace-nowrap transition-all',
              tab === t.id
                ? 'bg-[var(--color-teal-600)] text-white shadow-sm'
                : 'text-[var(--color-ink-muted)] hover:text-[var(--color-ink)] hover:bg-[var(--color-surface-2)]',
            )}
          >
            <t.icon className="h-4 w-4" />
            {t.label}
          </button>
        ))}
      </div>

      {/* Tab content */}
      {tab === 'overview' && (
        <div className="grid md:grid-cols-3 gap-4 enter">
          <VitalCard label="Blood pressure" value={latestBP ? `${latestBP.value_numeric}` : '—'} unit="mmHg" />
          <VitalCard label="Heart rate"     value={latestHR?.value_numeric ?? '—'} unit="bpm" />
          <VitalCard label="SpO₂"           value={latestSpO2?.value_numeric ?? '—'} unit="%" />
          <VitalCard label="Temperature"    value={latestTemp?.value_numeric ?? '—'} unit="°C" />

          <Card className="md:col-span-2">
            <CardHeader>
              <CardTitle>Emergency contact</CardTitle>
            </CardHeader>
            <CardBody>
              {patient.emergency_contact ? (
                <div className="flex items-start gap-4">
                  <AlertTriangle className="h-5 w-5 text-[var(--color-amber-500)] mt-0.5" />
                  <div>
                    <p className="font-medium">{patient.emergency_contact.name}</p>
                    <p className="text-sm text-[var(--color-ink-muted)]">
                      {patient.emergency_contact.relationship}
                      {' · '}
                      <span className="mono">{patient.emergency_contact.phone}</span>
                    </p>
                  </div>
                </div>
              ) : (
                <p className="text-sm text-[var(--color-ink-muted)]">No emergency contact on file.</p>
              )}
            </CardBody>
          </Card>

          <Card>
            <CardHeader><CardTitle>Insurance</CardTitle></CardHeader>
            <CardBody className="space-y-2 text-sm">
              <DL label="Payer" value={patient.payer_type} capitalize />
              {patient.insurance_provider && <DL label="Provider" value={patient.insurance_provider} />}
              {patient.insurance_number && <DL label="Policy #" value={patient.insurance_number} mono />}
            </CardBody>
          </Card>
        </div>
      )}

      {tab === 'timeline' && (
        <Card className="enter">
          <Table>
            <Thead>
              <tr>
                <Th>Date</Th>
                <Th>Type</Th>
                <Th>Provider</Th>
                <Th>Chief complaint</Th>
                <Th>Status</Th>
              </tr>
            </Thead>
            {(encountersQuery.data?.data.length ?? 0) === 0 ? (
              <Tbody><Tr><Td colSpan={5}><EmptyState title="No encounters yet" description="Past visits will appear here." /></Td></Tr></Tbody>
            ) : (
              <Tbody>
                {encountersQuery.data!.data.map((e) => (
                  <Tr key={e.id}>
                    <Td className="text-[13px]">{formatDateTime(e.started_at)}</Td>
                    <Td><Badge tone="info" className="capitalize">{e.encounter_type}</Badge></Td>
                    <Td>{e.provider?.user?.name ?? '—'}</Td>
                    <Td className="max-w-[280px] truncate">{e.chief_complaint ?? '—'}</Td>
                    <Td><Badge tone={statusTone(e.status)}>{e.status}</Badge></Td>
                  </Tr>
                ))}
              </Tbody>
            )}
          </Table>
        </Card>
      )}

      {tab === 'vitals' && (
        <Card className="enter">
          <CardHeader>
            <CardTitle>All observations</CardTitle>
            <p className="text-xs text-[var(--color-ink-muted)]">Chronological, newest first</p>
          </CardHeader>
          <Table>
            <Thead>
              <tr>
                <Th>Recorded</Th>
                <Th>Observation</Th>
                <Th className="text-right">Value</Th>
                <Th>Unit</Th>
              </tr>
            </Thead>
            {(vitals?.length ?? 0) === 0 ? (
              <Tbody><Tr><Td colSpan={4}><EmptyState title="No vitals yet" /></Td></Tr></Tbody>
            ) : (
              <Tbody>
                {[...(vitals ?? [])].reverse().map((o) => (
                  <Tr key={o.id}>
                    <Td className="text-[13px] text-[var(--color-ink-muted)]">{formatDateTime(o.recorded_at)}</Td>
                    <Td>{o.display_name}</Td>
                    <Td className="text-right mono">{o.value_numeric ?? o.value_text ?? '—'}</Td>
                    <Td className="text-[13px]">{o.unit ?? '—'}</Td>
                  </Tr>
                ))}
              </Tbody>
            )}
          </Table>
        </Card>
      )}

      {tab === 'prescriptions' && (
        <Card className="enter">
          <Table>
            <Thead>
              <tr>
                <Th>#</Th>
                <Th>Issued</Th>
                <Th>Doctor</Th>
                <Th>Status</Th>
              </tr>
            </Thead>
            {(rxQuery.data?.data.length ?? 0) === 0 ? (
              <Tbody><Tr><Td colSpan={4}><EmptyState title="No prescriptions" /></Td></Tr></Tbody>
            ) : (
              <Tbody>
                {rxQuery.data!.data.map((r) => (
                  <Tr key={r.id}>
                    <Td className="mono">{r.prescription_number}</Td>
                    <Td className="text-[13px]">{formatDate(r.issued_at)}</Td>
                    <Td>{r.provider?.user?.name ?? '—'}</Td>
                    <Td><Badge tone={statusTone(r.status)}>{r.status}</Badge></Td>
                  </Tr>
                ))}
              </Tbody>
            )}
          </Table>
        </Card>
      )}

      {tab === 'documents' && (
        <Card className="enter">
          <CardBody>
            <EmptyState
              title="No documents uploaded"
              description="Upload ID proofs, insurance cards, referral letters, or scanned reports."
            />
          </CardBody>
        </Card>
      )}
    </div>
  );
}

function VitalCard({ label, value, unit }: { label: string; value: string | number; unit: string }) {
  return (
    <Card>
      <CardBody>
        <p className="text-[11px] uppercase tracking-widest text-[var(--color-ink-muted)] mb-2">{label}</p>
        <div className="flex items-baseline gap-1.5">
          <p className="font-display text-3xl number">{value}</p>
          <span className="text-sm text-[var(--color-ink-muted)]">{unit}</span>
        </div>
      </CardBody>
    </Card>
  );
}

function InfoLine({ icon, label, value, mono = false }: { icon: React.ReactNode; label: string; value: string; mono?: boolean }) {
  return (
    <div>
      <div className="flex items-center gap-1.5 text-[11px] uppercase tracking-widest text-[var(--color-ink-muted)] mb-1">
        {icon}{label}
      </div>
      <p className={cn('text-sm', mono && 'mono')}>{value}</p>
    </div>
  );
}

function DL({ label, value, mono = false, capitalize = false }: { label: string; value: string; mono?: boolean; capitalize?: boolean }) {
  return (
    <div className="flex justify-between">
      <dt className="text-[var(--color-ink-muted)]">{label}</dt>
      <dd className={cn('font-medium', mono && 'mono', capitalize && 'capitalize')}>{value}</dd>
    </div>
  );
}

function Dot() { return <span className="h-1 w-1 rounded-full bg-[var(--color-hairline)]" />; }

function getLatestVital(obs: any[], code: string) {
  const v = obs.filter((o) => o.code === code).sort((a, b) => new Date(b.recorded_at).getTime() - new Date(a.recorded_at).getTime());
  return v[0];
}
