'use client';

import { useQuery } from '@tanstack/react-query';
import { BedDouble } from 'lucide-react';
import { Card, CardBody, CardHeader, CardTitle, PageHeader, StatCard, Badge } from '@/components/ui/surface';
import { bedsApi } from '@/lib/api/endpoints';
import { cn } from '@/lib/utils';

const STATUS_COLOR: Record<string, string> = {
  available:    'bg-emerald-50 border-emerald-200 text-emerald-800',
  occupied:     'bg-rose-50 border-rose-200 text-rose-800',
  cleaning:     'bg-amber-50 border-amber-200 text-amber-800',
  maintenance:  'bg-slate-100 border-slate-200 text-slate-700',
  reserved:     'bg-[var(--color-teal-50)] border-[var(--color-teal-100)] text-[var(--color-teal-700)]',
};

export default function WardsPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['beds', 'all'],
    queryFn: () => bedsApi.list({ per_page: 200 }),
  });

  const beds = data?.data ?? [];
  const byStatus = beds.reduce<Record<string, number>>((acc, b) => {
    acc[b.status] = (acc[b.status] ?? 0) + 1;
    return acc;
  }, {});

  // Group beds by ward → room for display
  const byWard: Record<string, Record<string, typeof beds>> = {};
  for (const bed of beds) {
    const wardName = (bed.room as any)?.ward?.name ?? 'Ward';
    const roomName = bed.room?.name ?? 'Room';
    byWard[wardName] ??= {};
    byWard[wardName][roomName] ??= [];
    byWard[wardName][roomName].push(bed);
  }

  return (
    <div>
      <PageHeader title="Wards & Beds" description="Live occupancy across the facility" />

      <div className="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <StatCard label="Total beds"   value={beds.length} icon={<BedDouble className="h-4 w-4" />} />
        <StatCard label="Occupied"     value={byStatus.occupied ?? 0}    tone="danger" />
        <StatCard label="Available"    value={byStatus.available ?? 0}   tone="success" />
        <StatCard label="Cleaning"     value={byStatus.cleaning ?? 0}    tone="warning" />
        <StatCard label="Maintenance"  value={byStatus.maintenance ?? 0} tone="neutral" />
      </div>

      {isLoading ? (
        <div className="skeleton h-96 w-full rounded-xl" />
      ) : (
        <div className="space-y-6">
          {Object.entries(byWard).map(([ward, rooms]) => (
            <Card key={ward} className="enter">
              <CardHeader>
                <CardTitle>{ward}</CardTitle>
                <Badge tone="neutral">
                  {Object.values(rooms).flat().length} beds
                </Badge>
              </CardHeader>
              <CardBody>
                <div className="space-y-5">
                  {Object.entries(rooms).map(([room, bedsInRoom]) => (
                    <div key={room}>
                      <p className="text-xs uppercase tracking-widest text-[var(--color-ink-muted)] mb-2 font-medium">
                        {room}
                      </p>
                      <div className="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2">
                        {bedsInRoom.map((bed) => (
                          <div
                            key={bed.id}
                            className={cn(
                              'aspect-square border-2 rounded-[10px] grid place-items-center text-center p-1 transition-all hover:scale-105 cursor-pointer',
                              STATUS_COLOR[bed.status] ?? 'bg-white border-[var(--color-hairline)]',
                            )}
                            title={`${bed.code} · ${bed.status}`}
                          >
                            <div>
                              <BedDouble className="h-4 w-4 mx-auto mb-0.5" />
                              <p className="text-[10px] font-medium mono leading-tight">
                                {bed.label.replace('Bed ', 'B')}
                              </p>
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
