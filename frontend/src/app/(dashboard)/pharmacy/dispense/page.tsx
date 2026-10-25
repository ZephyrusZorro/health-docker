'use client';

import { useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useQuery, useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import Link from 'next/link';
import { ArrowLeft, Plus, Trash2, Pill, Search } from 'lucide-react';
import { Card, CardBody, CardHeader, CardTitle, PageHeader } from '@/components/ui/surface';
import { Button } from '@/components/ui/button';
import { Field, Input, Select } from '@/components/ui/field';
import { pharmacyApi, patientsApi } from '@/lib/api/endpoints';
import { initials } from '@/lib/utils';

export default function DispensePage() {
  const router = useRouter();
  const sp = useSearchParams();

  const [patientSearch, setPatientSearch] = useState('');
  const [patientId, setPatientId] = useState<number | null>(
    sp.get('patient_id') ? Number(sp.get('patient_id')) : null,
  );
  const [items, setItems] = useState<Array<{ medication_id: string; quantity: string }>>([
    { medication_id: '', quantity: '' },
  ]);

  const patientsQ = useQuery({
    queryKey: ['patients', 'dispense-search', patientSearch],
    queryFn: () => patientsApi.list({ q: patientSearch, per_page: 8 } as any),
    enabled: patientSearch.length >= 2,
  });

  const selectedPatient = useQuery({
    queryKey: ['patient', patientId],
    queryFn: () => patientsApi.get(patientId!),
    enabled: !!patientId,
  });

  const medsQ = useQuery({
    queryKey: ['pharmacy', 'medications', 'all'],
    queryFn: () => pharmacyApi.medications({ per_page: 200 }),
  });

  const dispenseM = useMutation({
    mutationFn: () => pharmacyApi.dispense({
      patient_id: patientId!,
      items: items.filter((i) => i.medication_id && i.quantity).map((i) => ({
        medication_id: Number(i.medication_id),
        quantity: Number(i.quantity),
      })),
    }),
    onSuccess: (d) => {
      toast.success(`Dispensed: ${d.dispense_number}`);
      router.push(`/pharmacy` as any);
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? 'Dispense failed'),
  });

  return (
    <div className="max-w-3xl">
      <Link href="/pharmacy" className="text-sm text-[var(--color-ink-muted)] inline-flex items-center gap-1 mb-3 hover:text-[var(--color-ink)]">
        <ArrowLeft className="h-3.5 w-3.5" /> Back to pharmacy
      </Link>

      <PageHeader
        title="Dispense medication"
        description="FEFO strategy is applied automatically — earliest-expiry batches are consumed first."
      />

      <Card className="mb-6 enter">
        <CardHeader><CardTitle>Patient</CardTitle></CardHeader>
        <CardBody>
          {selectedPatient.data ? (
            <div className="flex items-center gap-3 p-3 rounded-[10px] bg-[var(--color-teal-50)] border border-[var(--color-teal-100)]">
              <div className="h-10 w-10 rounded-full bg-white text-[var(--color-teal-700)] grid place-items-center text-sm font-medium">
                {initials(`${selectedPatient.data.first_name} ${selectedPatient.data.last_name ?? ''}`)}
              </div>
              <div className="flex-1">
                <p className="font-medium">{selectedPatient.data.first_name} {selectedPatient.data.last_name}</p>
                <p className="text-xs text-[var(--color-ink-muted)] mono">{selectedPatient.data.uhid}</p>
              </div>
              <Button size="sm" variant="ghost" onClick={() => setPatientId(null)}>Change</Button>
            </div>
          ) : (
            <div>
              <div className="relative mb-2">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-[var(--color-ink-muted)]" />
                <Input
                  value={patientSearch}
                  onChange={(e) => setPatientSearch(e.target.value)}
                  placeholder="Search patient by name, UHID, phone…"
                  className="pl-10"
                />
              </div>
              {patientsQ.data?.data?.length ? (
                <div className="border border-[var(--color-hairline)] rounded-[10px] divide-y divide-[var(--color-hairline)] overflow-hidden">
                  {patientsQ.data.data.map((p) => (
                    <button
                      key={p.id}
                      type="button"
                      onClick={() => { setPatientId(p.id); setPatientSearch(''); }}
                      className="w-full text-left p-3 hover:bg-[var(--color-surface-2)] transition-colors"
                    >
                      <p className="font-medium text-sm">{p.first_name} {p.last_name}</p>
                      <p className="text-xs text-[var(--color-ink-muted)] mono">{p.uhid} · {p.phone}</p>
                    </button>
                  ))}
                </div>
              ) : patientSearch.length >= 2 ? (
                <p className="text-sm text-[var(--color-ink-muted)]">No patients found.</p>
              ) : null}
            </div>
          )}
        </CardBody>
      </Card>

      {patientId && (
        <Card className="mb-6 enter enter-1">
          <CardHeader>
            <CardTitle>Items to dispense</CardTitle>
            <Button size="sm" variant="secondary" onClick={() => setItems([...items, { medication_id: '', quantity: '' }])}>
              <Plus className="h-3 w-3" /> Add item
            </Button>
          </CardHeader>
          <CardBody className="space-y-3">
            {items.map((it, i) => (
              <div key={i} className="flex items-end gap-3">
                <Field label={i === 0 ? 'Medication' : undefined} className="flex-1">
                  <Select
                    value={it.medication_id}
                    onChange={(e) => { const next = [...items]; next[i].medication_id = e.target.value; setItems(next); }}
                  >
                    <option value="">Select medicine…</option>
                    {(medsQ.data?.data ?? []).map((m) => (
                      <option key={m.id} value={m.id}>
                        {m.brand_name || m.generic_name} · {m.strength} · {m.form}
                      </option>
                    ))}
                  </Select>
                </Field>
                <Field label={i === 0 ? 'Quantity' : undefined} className="w-28">
                  <Input
                    type="number" value={it.quantity}
                    onChange={(e) => { const next = [...items]; next[i].quantity = e.target.value; setItems(next); }}
                    placeholder="10"
                  />
                </Field>
                <button
                  type="button"
                  onClick={() => setItems(items.filter((_, idx) => idx !== i))}
                  className="h-10 w-10 grid place-items-center text-[var(--color-ink-muted)] hover:text-[var(--color-rose-500)]"
                  disabled={items.length === 1}
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            ))}
          </CardBody>
        </Card>
      )}

      <div className="flex justify-end">
        <Button
          onClick={() => dispenseM.mutate()}
          loading={dispenseM.isPending}
          disabled={!patientId || items.every((i) => !i.medication_id || !i.quantity)}
          size="lg"
        >
          <Pill className="h-4 w-4" />
          Dispense medications
        </Button>
      </div>
    </div>
  );
}
