'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Receipt } from 'lucide-react';
import {
  Card, PageHeader, Badge, Table, Thead, Tbody, Tr, Th, Td,
  SkeletonRows, EmptyState, StatCard,
} from '@/components/ui/surface';
import { billingApi } from '@/lib/api/endpoints';
import { formatMoney, formatDate, statusTone } from '@/lib/utils';

export default function BillingPage() {
  const [status, setStatus] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['invoices', status],
    queryFn: () => billingApi.invoices({ status: status || undefined, per_page: 50 }),
  });

  const invoices = data?.data ?? [];
  const totalDue = invoices.reduce((sum, inv) => sum + Number(inv.amount_due || 0), 0);
  const totalPaid = invoices.reduce((sum, inv) => sum + Number(inv.amount_paid || 0), 0);

  return (
    <div>
      <PageHeader title="Billing & Finance" description="Unified invoicing across OPD, IPD, pharmacy, and laboratory" />

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <StatCard label="Invoices shown" value={invoices.length} icon={<Receipt className="h-5 w-5" />} />
        <StatCard label="Outstanding" value={formatMoney(totalDue)} tone="warning" />
        <StatCard label="Collected"   value={formatMoney(totalPaid)} tone="success" />
      </div>

      <Card className="enter">
        <div className="p-4 flex items-center gap-3 border-b border-[var(--color-hairline)]">
          <select
            value={status}
            onChange={(e) => setStatus(e.target.value)}
            className="h-10 px-3 rounded-[10px] border border-[var(--color-hairline)] bg-white text-sm focus:outline-none focus:border-[var(--color-teal-500)]"
          >
            <option value="">All statuses</option>
            <option value="draft">Draft</option>
            <option value="finalised">Finalised</option>
            <option value="partially_paid">Partially paid</option>
            <option value="paid">Paid</option>
            <option value="void">Void</option>
          </select>
        </div>

        <Table>
          <Thead>
            <tr>
              <Th>Invoice #</Th>
              <Th>Patient</Th>
              <Th>Issued</Th>
              <Th className="text-right">Total</Th>
              <Th className="text-right">Paid</Th>
              <Th className="text-right">Due</Th>
              <Th>Status</Th>
            </tr>
          </Thead>
          {isLoading ? (
            <SkeletonRows rows={8} cols={7} />
          ) : invoices.length === 0 ? (
            <Tbody><Tr><Td colSpan={7}><EmptyState icon={<Receipt className="h-5 w-5" />} title="No invoices" /></Td></Tr></Tbody>
          ) : (
            <Tbody>
              {invoices.map((inv) => (
                <Tr key={inv.id}>
                  <Td className="mono text-[13px]">{inv.invoice_number}</Td>
                  <Td>
                    {inv.patient ? (
                      <Link href={`/patients/${inv.patient_id}` as any} className="hover:underline font-medium">
                        {inv.patient.first_name} {inv.patient.last_name}
                      </Link>
                    ) : '—'}
                  </Td>
                  <Td className="text-[13px]">{formatDate(inv.issue_date)}</Td>
                  <Td className="text-right mono">{formatMoney(inv.total_amount, inv.currency)}</Td>
                  <Td className="text-right mono text-[var(--color-emerald-500)]">
                    {formatMoney(inv.amount_paid, inv.currency)}
                  </Td>
                  <Td className={`text-right mono font-medium ${Number(inv.amount_due) > 0 ? 'text-[var(--color-amber-500)]' : ''}`}>
                    {formatMoney(inv.amount_due, inv.currency)}
                  </Td>
                  <Td>
                    <Badge tone={statusTone(inv.status)} className="capitalize">
                      {inv.status.replace(/_/g, ' ')}
                    </Badge>
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
