'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Pill, Plus, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Card, PageHeader, Badge, Table, Thead, Tbody, Tr, Th, Td,
  SkeletonRows, EmptyState,
} from '@/components/ui/surface';
import { pharmacyApi } from '@/lib/api/endpoints';
import { formatMoney, formatDate } from '@/lib/utils';

export default function PharmacyPage() {
  const [tab, setTab] = useState<'catalog' | 'dispenses'>('catalog');
  const [q, setQ] = useState('');

  const catalog = useQuery({
    queryKey: ['pharmacy', 'medications', q],
    queryFn: () => pharmacyApi.medications({ q: q || undefined, per_page: 50 } as any),
    enabled: tab === 'catalog',
  });

  const dispenses = useQuery({
    queryKey: ['pharmacy', 'dispenses'],
    queryFn: () => pharmacyApi.dispenses({ per_page: 50 }),
    enabled: tab === 'dispenses',
  });

  return (
    <div>
      <PageHeader
        title="Pharmacy"
        description="Drug catalog, batch inventory, and dispense history"
        actions={
          <Link href={'/pharmacy/dispense' as any}>
            <Button>
              <Plus className="h-4 w-4" />
              New dispense
            </Button>
          </Link>
        }
      />

      <div className="flex gap-1 mb-5">
        {(['catalog', 'dispenses'] as const).map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`px-4 h-10 rounded-[10px] text-sm font-medium capitalize transition-all ${
              tab === t
                ? 'bg-[var(--color-teal-600)] text-white'
                : 'text-[var(--color-ink-muted)] hover:bg-[var(--color-surface-2)]'
            }`}
          >
            {t}
          </button>
        ))}
      </div>

      {tab === 'catalog' && (
        <Card className="enter">
          <div className="p-4 flex items-center gap-3 border-b border-[var(--color-hairline)]">
            <div className="relative flex-1 max-w-md">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-[var(--color-ink-muted)]" />
              <input
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder="Search medicines by name or code…"
                className="w-full h-10 pl-10 pr-3 rounded-[10px] border border-[var(--color-hairline)] bg-white text-sm focus:outline-none focus:border-[var(--color-teal-500)]"
              />
            </div>
          </div>
          <Table>
            <Thead>
              <tr>
                <Th>Code</Th>
                <Th>Medicine</Th>
                <Th>Strength · Form</Th>
                <Th>Category</Th>
                <Th className="text-right">Prescription</Th>
              </tr>
            </Thead>
            {catalog.isLoading ? (
              <SkeletonRows rows={8} cols={5} />
            ) : (catalog.data?.data.length ?? 0) === 0 ? (
              <Tbody><Tr><Td colSpan={5}><EmptyState icon={<Pill className="h-5 w-5" />} title="No medicines" /></Td></Tr></Tbody>
            ) : (
              <Tbody>
                {catalog.data!.data.map((m) => (
                  <Tr key={m.id}>
                    <Td className="mono text-[13px]">{m.code}</Td>
                    <Td>
                      <div className="font-medium">{m.brand_name || m.generic_name}</div>
                      {m.brand_name && (
                        <div className="text-xs text-[var(--color-ink-muted)]">{m.generic_name}</div>
                      )}
                    </Td>
                    <Td className="text-[13px]">
                      {m.strength}{m.form ? ` · ${m.form}` : ''}
                    </Td>
                    <Td><Badge tone="neutral">{m.category}</Badge></Td>
                    <Td className="text-right">
                      {m.is_controlled ? (
                        <Badge tone="danger">Controlled</Badge>
                      ) : m.is_prescription_required ? (
                        <Badge tone="warning">Rx only</Badge>
                      ) : (
                        <Badge tone="success">OTC</Badge>
                      )}
                    </Td>
                  </Tr>
                ))}
              </Tbody>
            )}
          </Table>
        </Card>
      )}

      {tab === 'dispenses' && (
        <Card className="enter">
          <Table>
            <Thead>
              <tr>
                <Th>Dispense #</Th>
                <Th>Patient</Th>
                <Th>Dispensed</Th>
                <Th className="text-right">Amount</Th>
                <Th>Status</Th>
              </tr>
            </Thead>
            {dispenses.isLoading ? (
              <SkeletonRows rows={8} cols={5} />
            ) : (dispenses.data?.data.length ?? 0) === 0 ? (
              <Tbody><Tr><Td colSpan={5}><EmptyState title="No dispenses yet" /></Td></Tr></Tbody>
            ) : (
              <Tbody>
                {dispenses.data!.data.map((d) => (
                  <Tr key={d.id}>
                    <Td className="mono text-[13px]">{d.dispense_number}</Td>
                    <Td>
                      {d.patient ? (
                        <Link href={`/patients/${d.patient_id}` as any} className="hover:underline font-medium">
                          {d.patient.first_name} {d.patient.last_name}
                        </Link>
                      ) : '—'}
                    </Td>
                    <Td className="text-[13px]">{formatDate(d.dispensed_at)}</Td>
                    <Td className="text-right mono">{formatMoney(d.total_amount)}</Td>
                    <Td><Badge tone="success">{d.status}</Badge></Td>
                  </Tr>
                ))}
              </Tbody>
            )}
          </Table>
        </Card>
      )}
    </div>
  );
}
