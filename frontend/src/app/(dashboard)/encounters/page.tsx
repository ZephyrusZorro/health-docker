'use client';

import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { Stethoscope } from 'lucide-react';
import {
  Card, PageHeader, Badge, Table, Thead, Tbody, Tr, Th, Td,
  SkeletonRows, EmptyState,
} from '@/components/ui/surface';
import { encountersApi } from '@/lib/api/endpoints';
import { formatDateTime, statusTone } from '@/lib/utils';

export default function EncountersPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['encounters'],
    queryFn: () => encountersApi.list({ per_page: 50 }),
  });

  const list = data?.data ?? [];

  return (
    <div>
      <PageHeader title="Encounters" description="Outpatient and inpatient clinical encounters" />
      <Card className="enter">
        <Table>
          <Thead>
            <tr>
              <Th>Started</Th>
              <Th>Patient</Th>
              <Th>Provider</Th>
              <Th>Type</Th>
              <Th>Chief complaint</Th>
              <Th>Status</Th>
            </tr>
          </Thead>
          {isLoading ? (
            <SkeletonRows rows={8} cols={6} />
          ) : list.length === 0 ? (
            <Tbody><Tr><Td colSpan={6}><EmptyState icon={<Stethoscope className="h-5 w-5" />} title="No encounters" /></Td></Tr></Tbody>
          ) : (
            <Tbody>
              {list.map((e) => (
                <Tr key={e.id}>
                  <Td className="text-[13px]">{formatDateTime(e.started_at)}</Td>
                  <Td>
                    {e.patient ? (
                      <Link href={`/patients/${e.patient_id}` as any} className="hover:underline font-medium">
                        {e.patient.first_name} {e.patient.last_name}
                      </Link>
                    ) : '—'}
                  </Td>
                  <Td className="text-[13px]">{e.provider?.user?.name ?? '—'}</Td>
                  <Td><Badge tone="info" className="capitalize">{e.encounter_type}</Badge></Td>
                  <Td className="text-[13px] max-w-[280px] truncate">{e.chief_complaint ?? '—'}</Td>
                  <Td><Badge tone={statusTone(e.status)}>{e.status}</Badge></Td>
                </Tr>
              ))}
            </Tbody>
          )}
        </Table>
      </Card>
    </div>
  );
}
