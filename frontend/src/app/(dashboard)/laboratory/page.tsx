'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { FlaskConical, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Card, PageHeader, Badge, Table, Thead, Tbody, Tr, Th, Td,
  SkeletonRows, EmptyState,
} from '@/components/ui/surface';
import { labApi } from '@/lib/api/endpoints';
import { formatMoney, formatDateTime, statusTone } from '@/lib/utils';

export default function LabPage() {
  const [tab, setTab] = useState<'orders' | 'tests'>('orders');
  const [status, setStatus] = useState('');

  const orders = useQuery({
    queryKey: ['lab', 'orders', status],
    queryFn: () => labApi.orders({ status: status || undefined, per_page: 50 }),
    enabled: tab === 'orders',
  });

  const tests = useQuery({
    queryKey: ['lab', 'tests'],
    queryFn: () => labApi.tests({ per_page: 100 }),
    enabled: tab === 'tests',
  });

  return (
    <div>
      <PageHeader
        title="Laboratory"
        description="Orders, samples, and result turnaround"
      />

      <div className="flex gap-1 mb-5">
        {(['orders', 'tests'] as const).map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`px-4 h-10 rounded-[10px] text-sm font-medium capitalize transition-all ${
              tab === t
                ? 'bg-[var(--color-teal-600)] text-white'
                : 'text-[var(--color-ink-muted)] hover:bg-[var(--color-surface-2)]'
            }`}
          >
            {t === 'tests' ? 'Test catalog' : 'Orders'}
          </button>
        ))}
      </div>

      {tab === 'orders' && (
        <Card className="enter">
          <div className="p-4 flex items-center gap-3 border-b border-[var(--color-hairline)]">
            <select
              value={status}
              onChange={(e) => setStatus(e.target.value)}
              className="h-10 px-3 rounded-[10px] border border-[var(--color-hairline)] bg-white text-sm focus:outline-none focus:border-[var(--color-teal-500)]"
            >
              <option value="">All statuses</option>
              <option value="ordered">Ordered</option>
              <option value="sample_collected">Sample collected</option>
              <option value="in_progress">In progress</option>
              <option value="completed">Completed</option>
            </select>
          </div>
          <Table>
            <Thead>
              <tr>
                <Th>Order #</Th>
                <Th>Patient</Th>
                <Th>Tests</Th>
                <Th>Ordered</Th>
                <Th>Priority</Th>
                <Th>Status</Th>
              </tr>
            </Thead>
            {orders.isLoading ? (
              <SkeletonRows rows={8} cols={6} />
            ) : (orders.data?.data.length ?? 0) === 0 ? (
              <Tbody><Tr><Td colSpan={6}><EmptyState icon={<FlaskConical className="h-5 w-5" />} title="No lab orders" /></Td></Tr></Tbody>
            ) : (
              <Tbody>
                {orders.data!.data.map((o) => (
                  <Tr key={o.id}>
                    <Td className="mono text-[13px]">{o.order_number}</Td>
                    <Td>
                      {o.patient ? (
                        <Link href={`/patients/${o.patient_id}` as any} className="hover:underline font-medium">
                          {o.patient.first_name} {o.patient.last_name}
                        </Link>
                      ) : '—'}
                    </Td>
                    <Td className="text-[13px]">
                      {o.tests?.length ?? 0} test{(o.tests?.length ?? 0) === 1 ? '' : 's'}
                    </Td>
                    <Td className="text-[13px] text-[var(--color-ink-muted)]">{formatDateTime(o.ordered_at)}</Td>
                    <Td>
                      <Badge tone={o.priority === 'stat' ? 'danger' : o.priority === 'urgent' ? 'warning' : 'neutral'} className="capitalize">
                        {o.priority}
                      </Badge>
                    </Td>
                    <Td><Badge tone={statusTone(o.status)}>{o.status.replace(/_/g, ' ')}</Badge></Td>
                  </Tr>
                ))}
              </Tbody>
            )}
          </Table>
        </Card>
      )}

      {tab === 'tests' && (
        <Card className="enter">
          <Table>
            <Thead>
              <tr>
                <Th>Code</Th>
                <Th>Test</Th>
                <Th>Category</Th>
                <Th>Sample</Th>
                <Th>Reference range</Th>
                <Th className="text-right">Price</Th>
              </tr>
            </Thead>
            {tests.isLoading ? (
              <SkeletonRows rows={8} cols={6} />
            ) : (
              <Tbody>
                {(tests.data?.data ?? []).map((t) => (
                  <Tr key={t.id}>
                    <Td className="mono text-[13px]">{t.code}</Td>
                    <Td className="font-medium">{t.name}</Td>
                    <Td>
                      <Badge tone="neutral">{t.category}</Badge>
                    </Td>
                    <Td className="text-[13px]">{t.sample_type || '—'}</Td>
                    <Td className="text-[13px] mono text-[var(--color-ink-muted)]">
                      {t.reference_text ??
                        (t.reference_min != null && t.reference_max != null
                          ? `${t.reference_min} – ${t.reference_max}${t.unit ? ' ' + t.unit : ''}`
                          : '—')}
                    </Td>
                    <Td className="text-right mono">{formatMoney(t.price)}</Td>
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
