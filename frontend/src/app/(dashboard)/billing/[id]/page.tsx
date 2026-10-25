'use client';

import { useParams } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import Link from 'next/link';
import { toast } from 'sonner';
import { ArrowLeft, RefreshCw, CheckCircle2, Receipt, CreditCard } from 'lucide-react';
import {
  Card, CardBody, CardHeader, CardTitle, PageHeader, Badge, Table, Thead, Tbody, Tr, Th, Td,
} from '@/components/ui/surface';
import { Button } from '@/components/ui/button';
import { Field, Input, Select, Textarea } from '@/components/ui/field';
import { billingApi } from '@/lib/api/endpoints';
import { formatDate, formatDateTime, formatMoney, initials, statusTone } from '@/lib/utils';

export default function InvoiceDetailPage() {
  const { id } = useParams<{ id: string }>();
  const invoiceId = Number(id);
  const qc = useQueryClient();

  const { data: inv, isLoading } = useQuery({
    queryKey: ['invoice', invoiceId],
    queryFn: () => billingApi.getInvoice(invoiceId),
    enabled: !!invoiceId,
  });

  const [payment, setPayment] = useState({ amount: '', method: 'cash', reference: '' });
  const [discount, setDiscount] = useState({ amount: '', reason: '' });

  const refreshM = useMutation({
    mutationFn: () => billingApi.refresh(invoiceId),
    onSuccess: () => { toast.success('Invoice refreshed'); qc.invalidateQueries({ queryKey: ['invoice', invoiceId] }); },
  });

  const finaliseM = useMutation({
    mutationFn: () => billingApi.finalise(invoiceId),
    onSuccess: () => { toast.success('Invoice finalised'); qc.invalidateQueries({ queryKey: ['invoice', invoiceId] }); },
    onError: (e: any) => toast.error(e.response?.data?.message ?? 'Failed to finalise'),
  });

  const payM = useMutation({
    mutationFn: () => billingApi.payment({
      invoice_id: invoiceId,
      amount: Number(payment.amount),
      method: payment.method,
      reference: payment.reference || undefined,
    }),
    onSuccess: () => {
      toast.success('Payment recorded');
      setPayment({ amount: '', method: 'cash', reference: '' });
      qc.invalidateQueries({ queryKey: ['invoice', invoiceId] });
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? 'Payment failed'),
  });

  const discountM = useMutation({
    mutationFn: () => billingApi.applyDiscount(invoiceId, {
      amount: Number(discount.amount),
      reason: discount.reason,
    }),
    onSuccess: () => {
      toast.success('Discount applied');
      setDiscount({ amount: '', reason: '' });
      qc.invalidateQueries({ queryKey: ['invoice', invoiceId] });
    },
    onError: (e: any) => toast.error(e.response?.data?.message ?? 'Discount failed'),
  });

  if (isLoading || !inv) return <div className="skeleton h-96 w-full" />;

  const canFinalise = inv.status === 'draft';
  const canPay = ['finalised', 'partially_paid'].includes(inv.status) && Number(inv.amount_due) > 0;

  return (
    <div className="max-w-5xl">
      <Link href="/billing" className="text-sm text-[var(--color-ink-muted)] hover:text-[var(--color-ink)] inline-flex items-center gap-1 mb-3">
        <ArrowLeft className="h-3.5 w-3.5" /> Back to billing
      </Link>

      <PageHeader
        title={`Invoice ${inv.invoice_number}`}
        description={inv.patient ? `Issued for ${inv.patient.first_name} ${inv.patient.last_name ?? ''}` : undefined}
        actions={
          <>
            <Button variant="secondary" onClick={() => refreshM.mutate()} loading={refreshM.isPending}>
              <RefreshCw className="h-4 w-4" />
              Refresh
            </Button>
            {canFinalise && (
              <Button onClick={() => finaliseM.mutate()} loading={finaliseM.isPending}>
                <CheckCircle2 className="h-4 w-4" />
                Finalise
              </Button>
            )}
          </>
        }
      />

      <div className="grid lg:grid-cols-[1fr_340px] gap-6">
        <div className="space-y-6">
          {/* Patient + status */}
          <Card className="enter">
            <CardBody className="flex items-center gap-4 flex-wrap">
              <div className="h-12 w-12 rounded-full bg-[var(--color-teal-50)] text-[var(--color-teal-700)] grid place-items-center font-medium">
                {initials(`${inv.patient?.first_name ?? ''} ${inv.patient?.last_name ?? ''}`)}
              </div>
              <div className="flex-1 min-w-[200px]">
                {inv.patient && (
                  <Link href={`/patients/${inv.patient_id}` as any} className="font-medium hover:underline">
                    {inv.patient.first_name} {inv.patient.last_name}
                  </Link>
                )}
                <div className="text-xs text-[var(--color-ink-muted)] mono">{inv.patient?.uhid}</div>
              </div>
              <div className="text-right">
                <Badge tone={statusTone(inv.status)} className="capitalize">{inv.status.replace(/_/g, ' ')}</Badge>
                <p className="text-xs text-[var(--color-ink-muted)] mt-1">Issued {formatDate(inv.issue_date)}</p>
              </div>
            </CardBody>
          </Card>

          {/* Line items */}
          <Card className="enter enter-1">
            <CardHeader><CardTitle>Line items</CardTitle></CardHeader>
            <Table>
              <Thead>
                <tr>
                  <Th>Description</Th>
                  <Th>Category</Th>
                  <Th className="text-right">Qty</Th>
                  <Th className="text-right">Unit price</Th>
                  <Th className="text-right">Total</Th>
                </tr>
              </Thead>
              <Tbody>
                {(inv.items ?? []).map((it) => (
                  <Tr key={it.id}>
                    <Td>{it.description}</Td>
                    <Td><Badge tone="neutral">{it.category}</Badge></Td>
                    <Td className="text-right mono">{it.quantity}</Td>
                    <Td className="text-right mono">{formatMoney(it.unit_price, inv.currency)}</Td>
                    <Td className="text-right mono font-medium">{formatMoney(it.line_total, inv.currency)}</Td>
                  </Tr>
                ))}
              </Tbody>
            </Table>
            <div className="border-t border-[var(--color-hairline)] p-5 space-y-2 text-sm">
              <Totals label="Subtotal" value={formatMoney(inv.subtotal, inv.currency)} />
              <Totals label="Tax" value={formatMoney(inv.tax_amount, inv.currency)} />
              {Number(inv.discount_amount || 0) > 0 && (
                <Totals label="Discount" value={`- ${formatMoney(inv.discount_amount, inv.currency)}`} tone="success" />
              )}
              <div className="border-t border-[var(--color-hairline)] pt-2">
                <div className="flex justify-between font-display text-xl">
                  <span>Total</span>
                  <span className="mono">{formatMoney(inv.total_amount, inv.currency)}</span>
                </div>
                <Totals label="Paid" value={formatMoney(inv.amount_paid, inv.currency)} tone="success" />
                <div className="flex justify-between font-medium pt-1">
                  <span>Balance due</span>
                  <span className={`mono ${Number(inv.amount_due) > 0 ? 'text-[var(--color-amber-500)]' : 'text-[var(--color-emerald-500)]'}`}>
                    {formatMoney(inv.amount_due, inv.currency)}
                  </span>
                </div>
              </div>
            </div>
          </Card>

          {/* Payments history */}
          <Card className="enter enter-2">
            <CardHeader><CardTitle>Payment history</CardTitle></CardHeader>
            {(inv.payments?.length ?? 0) === 0 ? (
              <CardBody>
                <p className="text-sm text-[var(--color-ink-muted)]">No payments received yet.</p>
              </CardBody>
            ) : (
              <Table>
                <Thead>
                  <tr>
                    <Th>Receipt #</Th>
                    <Th>Received</Th>
                    <Th>Method</Th>
                    <Th className="text-right">Amount</Th>
                  </tr>
                </Thead>
                <Tbody>
                  {inv.payments!.map((p) => (
                    <Tr key={p.id}>
                      <Td className="mono text-[13px]">{p.receipt_number ?? '—'}</Td>
                      <Td className="text-[13px]">{formatDateTime(p.received_at)}</Td>
                      <Td className="capitalize">{p.method}</Td>
                      <Td className="text-right mono">{formatMoney(p.amount, inv.currency)}</Td>
                    </Tr>
                  ))}
                </Tbody>
              </Table>
            )}
          </Card>
        </div>

        {/* Right: action rail */}
        <aside className="space-y-4 lg:sticky lg:top-24 lg:self-start">
          {canPay && (
            <Card className="enter">
              <CardHeader>
                <CardTitle className="flex items-center gap-2"><CreditCard className="h-4 w-4" /> Collect payment</CardTitle>
              </CardHeader>
              <CardBody className="space-y-3">
                <Field label="Amount" required>
                  <Input
                    type="number" step="0.01" value={payment.amount}
                    placeholder={String(inv.amount_due)}
                    onChange={(e) => setPayment({ ...payment, amount: e.target.value })}
                  />
                </Field>
                <Field label="Method" required>
                  <Select value={payment.method} onChange={(e) => setPayment({ ...payment, method: e.target.value })}>
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="upi">UPI</option>
                    <option value="bank_transfer">Bank transfer</option>
                    <option value="cheque">Cheque</option>
                    <option value="insurance">Insurance</option>
                  </Select>
                </Field>
                <Field label="Reference / txn ID">
                  <Input value={payment.reference} onChange={(e) => setPayment({ ...payment, reference: e.target.value })} />
                </Field>
                <Button onClick={() => payM.mutate()} loading={payM.isPending} className="w-full">
                  <Receipt className="h-4 w-4" />
                  Record payment
                </Button>
              </CardBody>
            </Card>
          )}

          {['draft', 'finalised', 'partially_paid'].includes(inv.status) && (
            <Card className="enter enter-1">
              <CardHeader><CardTitle>Apply discount</CardTitle></CardHeader>
              <CardBody className="space-y-3">
                <Field label="Amount" required>
                  <Input type="number" step="0.01" value={discount.amount} onChange={(e) => setDiscount({ ...discount, amount: e.target.value })} />
                </Field>
                <Field label="Reason" required hint="Required for audit trail">
                  <Textarea rows={2} value={discount.reason} onChange={(e) => setDiscount({ ...discount, reason: e.target.value })} />
                </Field>
                <Button onClick={() => discountM.mutate()} loading={discountM.isPending} variant="secondary" className="w-full">
                  Apply discount
                </Button>
                <p className="text-xs text-[var(--color-ink-muted)]">
                  Discounts above ₹{500} require approval from a hospital admin.
                </p>
              </CardBody>
            </Card>
          )}
        </aside>
      </div>
    </div>
  );
}

function Totals({ label, value, tone }: { label: string; value: string; tone?: 'success' }) {
  return (
    <div className="flex justify-between">
      <span className="text-[var(--color-ink-muted)]">{label}</span>
      <span className={`mono ${tone === 'success' ? 'text-[var(--color-emerald-500)]' : ''}`}>{value}</span>
    </div>
  );
}
