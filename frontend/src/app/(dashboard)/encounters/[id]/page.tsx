'use client';

import { useParams, useRouter } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import Link from 'next/link';
import { toast } from 'sonner';
import {
  ArrowLeft, Activity, Heart, Pill, FileText, CheckCircle2, Plus, Trash2,
} from 'lucide-react';
import {
  Card, CardBody, CardHeader, CardTitle, PageHeader, Badge,
} from '@/components/ui/surface';
import { Button } from '@/components/ui/button';
import { Field, Input, Textarea, Select } from '@/components/ui/field';
import { encountersApi, prescriptionsApi, pharmacyApi } from '@/lib/api/endpoints';
import { formatDateTime, age, initials, statusTone } from '@/lib/utils';
import { cn } from '@/lib/utils';

export default function EncounterDetailPage() {
  const { id } = useParams<{ id: string }>();
  const encounterId = Number(id);
  const qc = useQueryClient();
  const router = useRouter();

  const { data: encounter, isLoading } = useQuery({
    queryKey: ['encounter', encounterId],
    queryFn: () => encountersApi.get(encounterId),
    enabled: !!encounterId,
  });

  const observations = useQuery({
    queryKey: ['encounter', encounterId, 'observations'],
    queryFn: () => encountersApi.observations(encounterId),
    enabled: !!encounterId,
  });

  const medsCatalog = useQuery({
    queryKey: ['pharmacy', 'medications', 'catalog-lite'],
    queryFn: () => pharmacyApi.medications({ per_page: 200 }),
  });

  // --- Vitals state ------------------------------------------------------
  const [vitals, setVitals] = useState({
    systolic: '', diastolic: '', heart_rate: '', temperature_c: '',
    spo2: '', respiratory_rate: '', weight_kg: '', height_cm: '',
  });

  const vitalsMutation = useMutation({
    mutationFn: () => encountersApi.recordVitals(encounterId, {
      systolic: Number(vitals.systolic) || undefined,
      diastolic: Number(vitals.diastolic) || undefined,
      heart_rate: Number(vitals.heart_rate) || undefined,
      temperature_c: Number(vitals.temperature_c) || undefined,
      spo2: Number(vitals.spo2) || undefined,
      respiratory_rate: Number(vitals.respiratory_rate) || undefined,
      weight_kg: Number(vitals.weight_kg) || undefined,
      height_cm: Number(vitals.height_cm) || undefined,
    } as any),
    onSuccess: () => {
      toast.success('Vitals recorded');
      setVitals({ systolic: '', diastolic: '', heart_rate: '', temperature_c: '', spo2: '', respiratory_rate: '', weight_kg: '', height_cm: '' });
      qc.invalidateQueries({ queryKey: ['encounter', encounterId, 'observations'] });
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? 'Failed to record vitals'),
  });

  // --- Rx composer state -------------------------------------------------
  const [rxItems, setRxItems] = useState<Array<{
    medication_id: string; dosage: string; frequency: string;
    duration_days: string; quantity: string; route: string; instructions: string;
  }>>([{ medication_id: '', dosage: '', frequency: '', duration_days: '', quantity: '', route: 'oral', instructions: '' }]);
  const [rxNotes, setRxNotes] = useState('');

  const rxMutation = useMutation({
    mutationFn: () => prescriptionsApi.create({
      patient_id: encounter!.patient_id,
      provider_id: encounter!.provider_id,
      encounter_id: encounterId,
      notes: rxNotes || undefined,
      items: rxItems.filter((i) => i.medication_id).map((i) => ({
        medication_id: Number(i.medication_id),
        dosage: i.dosage,
        frequency: i.frequency,
        duration_days: Number(i.duration_days) || 1,
        quantity: Number(i.quantity) || 1,
        route: i.route,
        instructions: i.instructions || undefined,
      })),
    }),
    onSuccess: (rx) => {
      toast.success(`Prescription ${rx.prescription_number} created`);
      setRxItems([{ medication_id: '', dosage: '', frequency: '', duration_days: '', quantity: '', route: 'oral', instructions: '' }]);
      setRxNotes('');
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? 'Failed to save prescription'),
  });

  const closeMutation = useMutation({
    mutationFn: () => encountersApi.close(encounterId),
    onSuccess: () => {
      toast.success('Encounter closed');
      qc.invalidateQueries({ queryKey: ['encounter', encounterId] });
      router.push('/encounters');
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? 'Failed to close'),
  });

  if (isLoading || !encounter) {
    return <div className="skeleton h-96 w-full" />;
  }

  const closed = encounter.status === 'closed';

  return (
    <div className="max-w-6xl">
      <Link href="/encounters" className="text-sm text-[var(--color-ink-muted)] hover:text-[var(--color-ink)] inline-flex items-center gap-1 mb-3">
        <ArrowLeft className="h-3.5 w-3.5" /> Back to encounters
      </Link>

      {/* Header card */}
      <Card className="mb-6 enter">
        <CardBody className="flex items-start gap-5 flex-wrap">
          <div className="h-14 w-14 rounded-full bg-[var(--color-teal-50)] text-[var(--color-teal-700)] grid place-items-center font-display text-xl shrink-0">
            {initials(`${encounter.patient?.first_name} ${encounter.patient?.last_name ?? ''}`)}
          </div>
          <div className="flex-1 min-w-[220px]">
            <div className="flex items-center gap-2 mb-1 flex-wrap">
              <h1 className="font-display text-2xl tracking-tight">
                {encounter.patient?.first_name} {encounter.patient?.last_name}
              </h1>
              <Badge tone="info" className="capitalize">{encounter.encounter_type}</Badge>
              <Badge tone={statusTone(encounter.status)}>{encounter.status}</Badge>
            </div>
            <p className="text-sm text-[var(--color-ink-muted)] flex flex-wrap gap-3">
              <span className="mono text-[var(--color-teal-700)]">{encounter.patient?.uhid}</span>
              <span>·</span>
              <span>{age(encounter.patient?.date_of_birth)} yrs · <span className="capitalize">{encounter.patient?.gender}</span></span>
              <span>·</span>
              <span>Started {formatDateTime(encounter.started_at)}</span>
              <span>·</span>
              <span>With {encounter.provider?.user?.name}</span>
            </p>
            {encounter.chief_complaint && (
              <p className="text-sm mt-3"><span className="text-[var(--color-ink-muted)]">Chief complaint:</span> {encounter.chief_complaint}</p>
            )}
          </div>
          {!closed && (
            <Button onClick={() => closeMutation.mutate()} loading={closeMutation.isPending} variant="outline">
              <CheckCircle2 className="h-4 w-4" />
              Close encounter
            </Button>
          )}
        </CardBody>
      </Card>

      <div className="grid lg:grid-cols-2 gap-6">
        {/* Vitals capture */}
        <Card className={cn('enter enter-1', closed && 'opacity-60 pointer-events-none')}>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Heart className="h-4 w-4 text-[var(--color-rose-500)]" />
              Record vitals
            </CardTitle>
          </CardHeader>
          <CardBody>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Systolic" hint="mmHg">
                <Input type="number" value={vitals.systolic} onChange={(e) => setVitals({ ...vitals, systolic: e.target.value })} placeholder="120" />
              </Field>
              <Field label="Diastolic" hint="mmHg">
                <Input type="number" value={vitals.diastolic} onChange={(e) => setVitals({ ...vitals, diastolic: e.target.value })} placeholder="80" />
              </Field>
              <Field label="Heart rate" hint="bpm">
                <Input type="number" value={vitals.heart_rate} onChange={(e) => setVitals({ ...vitals, heart_rate: e.target.value })} placeholder="72" />
              </Field>
              <Field label="Temperature" hint="°C">
                <Input type="number" step="0.1" value={vitals.temperature_c} onChange={(e) => setVitals({ ...vitals, temperature_c: e.target.value })} placeholder="37.0" />
              </Field>
              <Field label="SpO₂" hint="%">
                <Input type="number" value={vitals.spo2} onChange={(e) => setVitals({ ...vitals, spo2: e.target.value })} placeholder="98" />
              </Field>
              <Field label="Respiratory rate" hint="breaths/min">
                <Input type="number" value={vitals.respiratory_rate} onChange={(e) => setVitals({ ...vitals, respiratory_rate: e.target.value })} placeholder="16" />
              </Field>
              <Field label="Weight" hint="kg">
                <Input type="number" step="0.1" value={vitals.weight_kg} onChange={(e) => setVitals({ ...vitals, weight_kg: e.target.value })} />
              </Field>
              <Field label="Height" hint="cm">
                <Input type="number" value={vitals.height_cm} onChange={(e) => setVitals({ ...vitals, height_cm: e.target.value })} />
              </Field>
            </div>
            <Button onClick={() => vitalsMutation.mutate()} loading={vitalsMutation.isPending} className="mt-4 w-full">
              <Activity className="h-4 w-4" />
              Record vitals
            </Button>
          </CardBody>
        </Card>

        {/* Recent observations ladder */}
        <Card className="enter enter-2">
          <CardHeader>
            <CardTitle>Captured observations</CardTitle>
            <Badge tone="neutral">{observations.data?.length ?? 0}</Badge>
          </CardHeader>
          <CardBody>
            {(observations.data?.length ?? 0) === 0 ? (
              <p className="text-sm text-[var(--color-ink-muted)] py-6 text-center">No observations yet.</p>
            ) : (
              <div className="space-y-2 max-h-[400px] overflow-y-auto">
                {[...(observations.data ?? [])].reverse().map((o) => (
                  <div key={o.id} className="flex items-center justify-between p-2.5 rounded-[8px] bg-[var(--color-surface-2)]">
                    <div>
                      <p className="text-sm font-medium">{o.display_name}</p>
                      <p className="text-[11px] text-[var(--color-ink-muted)] mono">{formatDateTime(o.recorded_at)}</p>
                    </div>
                    <div className="text-right">
                      <p className="font-display text-xl number">{o.value_numeric ?? o.value_text}</p>
                      <p className="text-[10px] text-[var(--color-ink-muted)]">{o.unit}</p>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </CardBody>
        </Card>
      </div>

      {/* Rx composer */}
      <Card className={cn('mt-6 enter enter-3', closed && 'opacity-60 pointer-events-none')}>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Pill className="h-4 w-4 text-[var(--color-teal-600)]" />
            Write prescription
          </CardTitle>
          <Button
            size="sm" variant="secondary"
            onClick={() => setRxItems([...rxItems, { medication_id: '', dosage: '', frequency: '', duration_days: '', quantity: '', route: 'oral', instructions: '' }])}
          >
            <Plus className="h-3 w-3" /> Add medicine
          </Button>
        </CardHeader>
        <CardBody className="space-y-3">
          {rxItems.map((item, i) => (
            <div key={i} className="p-4 rounded-[12px] border border-[var(--color-hairline)] bg-[var(--color-surface-2)]/40 relative">
              <button
                onClick={() => setRxItems(rxItems.filter((_, idx) => idx !== i))}
                className="absolute top-2 right-2 p-1.5 text-[var(--color-ink-muted)] hover:text-[var(--color-rose-500)]"
                type="button"
              >
                <Trash2 className="h-3.5 w-3.5" />
              </button>
              <div className="grid md:grid-cols-3 gap-3">
                <Field label="Medication" className="md:col-span-3">
                  <Select
                    value={item.medication_id}
                    onChange={(e) => {
                      const next = [...rxItems];
                      next[i].medication_id = e.target.value;
                      setRxItems(next);
                    }}
                  >
                    <option value="">Select medicine…</option>
                    {(medsCatalog.data?.data ?? []).map((m) => (
                      <option key={m.id} value={m.id}>
                        {m.brand_name || m.generic_name} · {m.strength} · {m.form}
                      </option>
                    ))}
                  </Select>
                </Field>
                <Field label="Dosage">
                  <Input placeholder="1 tablet" value={item.dosage} onChange={(e) => { const n = [...rxItems]; n[i].dosage = e.target.value; setRxItems(n); }} />
                </Field>
                <Field label="Frequency">
                  <Input placeholder="TDS / 1-0-1" value={item.frequency} onChange={(e) => { const n = [...rxItems]; n[i].frequency = e.target.value; setRxItems(n); }} />
                </Field>
                <Field label="Duration (days)">
                  <Input type="number" placeholder="5" value={item.duration_days} onChange={(e) => { const n = [...rxItems]; n[i].duration_days = e.target.value; setRxItems(n); }} />
                </Field>
                <Field label="Quantity">
                  <Input type="number" placeholder="15" value={item.quantity} onChange={(e) => { const n = [...rxItems]; n[i].quantity = e.target.value; setRxItems(n); }} />
                </Field>
                <Field label="Route">
                  <Select value={item.route} onChange={(e) => { const n = [...rxItems]; n[i].route = e.target.value; setRxItems(n); }}>
                    <option value="oral">Oral</option>
                    <option value="iv">IV</option>
                    <option value="im">IM</option>
                    <option value="sc">SC</option>
                    <option value="topical">Topical</option>
                    <option value="inhalation">Inhalation</option>
                  </Select>
                </Field>
                <Field label="Special instructions" className="md:col-span-1">
                  <Input placeholder="After food" value={item.instructions} onChange={(e) => { const n = [...rxItems]; n[i].instructions = e.target.value; setRxItems(n); }} />
                </Field>
              </div>
            </div>
          ))}

          <Field label="Doctor's notes">
            <Textarea rows={2} value={rxNotes} onChange={(e) => setRxNotes(e.target.value)} placeholder="Diet, lifestyle, follow-up in 7 days…" />
          </Field>

          <Button onClick={() => rxMutation.mutate()} loading={rxMutation.isPending} className="w-full" size="lg">
            <Pill className="h-4 w-4" />
            Save prescription
          </Button>
        </CardBody>
      </Card>

      {/* Clinical summary */}
      <Card className="mt-6 enter enter-4">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <FileText className="h-4 w-4" />
            Clinical summary
          </CardTitle>
        </CardHeader>
        <CardBody>
          {encounter.clinical_summary ? (
            <p className="text-sm leading-relaxed whitespace-pre-wrap">{encounter.clinical_summary}</p>
          ) : (
            <p className="text-sm text-[var(--color-ink-muted)]">No clinical summary recorded yet.</p>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
