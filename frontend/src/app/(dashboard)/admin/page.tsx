'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Shield, UserPlus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Card, PageHeader, Badge, Table, Thead, Tbody, Tr, Th, Td,
  SkeletonRows, EmptyState,
} from '@/components/ui/surface';
import { adminApi } from '@/lib/api/endpoints';
import { formatDateTime, initials } from '@/lib/utils';

export default function AdminPage() {
  const [tab, setTab] = useState<'users' | 'roles'>('users');

  const usersQ = useQuery({
    queryKey: ['admin', 'users'],
    queryFn: () => adminApi.users({ per_page: 50 }),
    enabled: tab === 'users',
  });

  const rolesQ = useQuery({
    queryKey: ['admin', 'roles'],
    queryFn: () => adminApi.roles(),
    enabled: tab === 'roles',
  });

  return (
    <div>
      <PageHeader
        title="Administration"
        description="Users, roles, and system configuration"
        actions={
          <Button variant="secondary">
            <UserPlus className="h-4 w-4" />
            New user
          </Button>
        }
      />

      <div className="flex gap-1 mb-5">
        {(['users', 'roles'] as const).map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`px-4 h-10 rounded-[10px] text-sm font-medium capitalize transition-all ${
              tab === t
                ? 'bg-[var(--color-teal-600)] text-white'
                : 'text-[var(--color-ink-muted)] hover:bg-[var(--color-surface-2)]'
            }`}
          >
            {t === 'roles' ? 'Roles & permissions' : 'Users'}
          </button>
        ))}
      </div>

      {tab === 'users' && (
        <Card className="enter">
          <Table>
            <Thead>
              <tr>
                <Th>User</Th>
                <Th>Email</Th>
                <Th>Role</Th>
                <Th>Last login</Th>
                <Th>Status</Th>
              </tr>
            </Thead>
            {usersQ.isLoading ? (
              <SkeletonRows rows={8} cols={5} />
            ) : (
              <Tbody>
                {(usersQ.data?.data ?? []).map((u) => (
                  <Tr key={u.id}>
                    <Td>
                      <div className="flex items-center gap-3">
                        <div className="h-8 w-8 rounded-full bg-[var(--color-teal-50)] text-[var(--color-teal-700)] grid place-items-center text-xs font-medium">
                          {initials(u.name)}
                        </div>
                        <span className="font-medium">{u.name}</span>
                      </div>
                    </Td>
                    <Td className="text-[13px]">{u.email}</Td>
                    <Td>
                      {u.roles?.map((r) => (
                        <Badge key={r.id} tone="info" className="capitalize">
                          {r.display_name ?? r.name.replace(/_/g, ' ')}
                        </Badge>
                      ))}
                    </Td>
                    <Td className="text-[13px] text-[var(--color-ink-muted)]">
                      {u.last_login_at ? formatDateTime(u.last_login_at) : 'Never'}
                    </Td>
                    <Td>
                      {u.is_active ? <Badge tone="success">Active</Badge> : <Badge tone="danger">Inactive</Badge>}
                    </Td>
                  </Tr>
                ))}
              </Tbody>
            )}
          </Table>
        </Card>
      )}

      {tab === 'roles' && (
        <div className="grid md:grid-cols-2 gap-4 enter">
          {rolesQ.isLoading ? (
            <div className="skeleton h-40" />
          ) : (
            (rolesQ.data ?? []).map((r: any) => (
              <Card key={r.id}>
                <div className="p-5">
                  <div className="flex items-start gap-3 mb-3">
                    <Shield className="h-5 w-5 text-[var(--color-teal-600)] shrink-0 mt-0.5" />
                    <div className="flex-1">
                      <h3 className="font-display text-lg">{r.display_name ?? r.name}</h3>
                      <p className="text-xs text-[var(--color-ink-muted)] mono">{r.name}</p>
                    </div>
                    <Badge tone="neutral">{r.permissions?.length ?? 0} perms</Badge>
                  </div>
                  <p className="text-sm text-[var(--color-ink-muted)]">{r.description}</p>
                </div>
              </Card>
            ))
          )}
        </div>
      )}
    </div>
  );
}
