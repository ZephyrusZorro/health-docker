'use client';

import { useQuery } from '@tanstack/react-query';
import { ClipboardList } from 'lucide-react';
import {
  Card, PageHeader, Badge, Table, Thead, Tbody, Tr, Th, Td,
  SkeletonRows, EmptyState,
} from '@/components/ui/surface';
import { adminApi } from '@/lib/api/endpoints';
import { formatDateTime } from '@/lib/utils';

export default function AuditPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['admin', 'audit'],
    queryFn: () => adminApi.auditLogs({ per_page: 100 }),
  });

  const logs = data?.data ?? [];

  return (
    <div>
      <PageHeader
        title="Audit logs"
        description="Every sensitive action is recorded. Immutable, append-only."
      />
      <Card className="enter">
        <Table>
          <Thead>
            <tr>
              <Th>Time</Th>
              <Th>User</Th>
              <Th>Action</Th>
              <Th>Resource</Th>
              <Th>IP</Th>
            </tr>
          </Thead>
          {isLoading ? (
            <SkeletonRows rows={10} cols={5} />
          ) : logs.length === 0 ? (
            <Tbody><Tr><Td colSpan={5}><EmptyState icon={<ClipboardList className="h-5 w-5" />} title="No audit events yet" /></Td></Tr></Tbody>
          ) : (
            <Tbody>
              {logs.map((l) => (
                <Tr key={l.id}>
                  <Td className="text-[13px] text-[var(--color-ink-muted)] mono">
                    {formatDateTime(l.occurred_at)}
                  </Td>
                  <Td className="text-[13px]">
                    {l.user ? `${l.user.name} (${l.user.email})` : 'System'}
                  </Td>
                  <Td>
                    <Badge tone={l.action === 'delete' ? 'danger' : l.action === 'create' ? 'success' : 'info'} className="capitalize">
                      {l.action}
                    </Badge>
                  </Td>
                  <Td className="text-[13px]">
                    <span className="mono text-[var(--color-ink-muted)]">
                      {l.auditable_type.split('\\').pop()}
                    </span>
                    <span className="mono">#{l.auditable_id}</span>
                  </Td>
                  <Td className="text-[13px] mono text-[var(--color-ink-muted)]">{l.ip_address ?? '—'}</Td>
                </Tr>
              ))}
            </Tbody>
          )}
        </Table>
      </Card>
    </div>
  );
}
