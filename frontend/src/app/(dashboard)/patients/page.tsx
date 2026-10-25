'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useSearchParams } from 'next/navigation';
import { UserPlus, Users, Filter, Phone } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Card, PageHeader, Table, Thead, Tbody, Tr, Th, Td,
  SkeletonRows, EmptyState, Badge,
} from '@/components/ui/surface';
import { patientsApi } from '@/lib/api/endpoints';
import { age, formatDate, initials } from '@/lib/utils';

export default function PatientsPage() {
  const sp = useSearchParams();
  const [q, setQ]     = useState(sp.get('q') ?? '');
  const [page, setPage] = useState(1);
  const [payer, setPayer] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['patients', { q, page, payer }],
    queryFn: () => patientsApi.list({
      q: q || undefined,
      payer_type: payer || undefined,
      page,
      per_page: 25,
    } as any),
  });

  const patients = data?.data ?? [];
  const meta = data?.meta;

  return (
    <div>
      <PageHeader
        title="Patients"
        description={
          meta
            ? `${meta.total.toLocaleString()} patients across the facility`
            : 'Patient master records'
        }
        actions={
          <Link href={'/patients/new' as any}>
            <Button>
              <UserPlus className="h-4 w-4" />
              New patient
            </Button>
          </Link>
        }
      />

      {/* Filters ---------------------------------------------------- */}
      <Card className="mb-5 enter">
        <div className="p-4 flex items-center gap-3 flex-wrap">
          <input
            value={q}
            onChange={(e) => { setQ(e.target.value); setPage(1); }}
            placeholder="Search by name, UHID, phone, email…"
            className="flex-1 min-w-[260px] h-10 px-4 rounded-[10px] border border-[var(--color-hairline)] bg-white text-sm focus:border-[var(--color-teal-500)] focus:ring-2 focus:ring-[var(--color-teal-100)] focus:outline-none"
          />
          <div className="flex items-center gap-2 text-sm text-[var(--color-ink-muted)]">
            <Filter className="h-4 w-4" />
            <select
              value={payer}
              onChange={(e) => { setPayer(e.target.value); setPage(1); }}
              className="h-10 px-3 rounded-[10px] border border-[var(--color-hairline)] bg-white text-sm focus:outline-none focus:border-[var(--color-teal-500)]"
            >
              <option value="">All payers</option>
              <option value="self">Self-pay</option>
              <option value="insurance">Insurance</option>
              <option value="corporate">Corporate</option>
              <option value="government">Government</option>
              <option value="charity">Charity</option>
            </select>
          </div>
        </div>
      </Card>

      {/* Table ------------------------------------------------------ */}
      <Card className="enter enter-2">
        <Table>
          <Thead>
            <tr>
              <Th>Patient</Th>
              <Th>UHID</Th>
              <Th>Age · Sex</Th>
              <Th>Contact</Th>
              <Th>Payer</Th>
              <Th>Registered</Th>
            </tr>
          </Thead>
          {isLoading ? (
            <SkeletonRows rows={8} cols={6} />
          ) : patients.length === 0 ? (
            <Tbody>
              <Tr>
                <Td colSpan={6}>
                  <EmptyState
                    icon={<Users className="h-5 w-5" />}
                    title={q ? 'No matches' : 'No patients yet'}
                    description={
                      q ? 'Try a different search term.' : 'Register your first patient to get started.'
                    }
                  />
                </Td>
              </Tr>
            </Tbody>
          ) : (
            <Tbody>
              {patients.map((p) => (
                <Tr key={p.id} className="cursor-pointer">
                  <Td>
                    <Link href={`/patients/${p.id}` as any} className="flex items-center gap-3">
                      <div className="h-9 w-9 rounded-full bg-[var(--color-teal-50)] text-[var(--color-teal-700)] grid place-items-center font-medium text-xs shrink-0">
                        {initials(`${p.first_name} ${p.last_name ?? ''}`)}
                      </div>
                      <div className="min-w-0">
                        <p className="font-medium truncate">
                          {p.first_name} {p.last_name}
                          {p.is_vip && (
                            <Badge tone="warning" className="ml-2 text-[10px]">VIP</Badge>
                          )}
                        </p>
                        {p.blood_group && (
                          <p className="text-xs text-[var(--color-ink-muted)]">
                            Blood: <span className="mono">{p.blood_group}</span>
                          </p>
                        )}
                      </div>
                    </Link>
                  </Td>
                  <Td className="mono text-[13px]">{p.uhid}</Td>
                  <Td className="text-[13px]">
                    {age(p.date_of_birth)} yrs ·{' '}
                    <span className="capitalize">{p.gender}</span>
                  </Td>
                  <Td>
                    {p.phone && (
                      <div className="flex items-center gap-1.5 text-[13px] mono">
                        <Phone className="h-3 w-3 text-[var(--color-ink-muted)]" />
                        {p.phone}
                      </div>
                    )}
                    {p.email && (
                      <div className="text-xs text-[var(--color-ink-muted)] truncate max-w-[200px]">
                        {p.email}
                      </div>
                    )}
                  </Td>
                  <Td>
                    <Badge
                      tone={
                        p.payer_type === 'self' ? 'neutral'
                          : p.payer_type === 'charity' ? 'success'
                          : 'info'
                      }
                      className="capitalize"
                    >
                      {p.payer_type}
                    </Badge>
                  </Td>
                  <Td className="text-[13px] text-[var(--color-ink-muted)]">
                    {formatDate(p.registered_at)}
                  </Td>
                </Tr>
              ))}
            </Tbody>
          )}
        </Table>

        {meta && meta.last_page > 1 && (
          <div className="flex items-center justify-between px-5 py-3 border-t border-[var(--color-hairline)] text-sm">
            <span className="text-[var(--color-ink-muted)]">
              Page {meta.current_page} of {meta.last_page} · {meta.total.toLocaleString()} total
            </span>
            <div className="flex items-center gap-2">
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page === 1}
                onClick={() => setPage(page - 1)}
              >
                Previous
              </Button>
              <Button
                variant="secondary"
                size="sm"
                disabled={meta.current_page === meta.last_page}
                onClick={() => setPage(page + 1)}
              >
                Next
              </Button>
            </div>
          </div>
        )}
      </Card>
    </div>
  );
}
