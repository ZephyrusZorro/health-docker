import { cn } from '@/lib/utils';
import { forwardRef, type HTMLAttributes } from 'react';

// ---------- Card primitives -------------------------------------------
export const Card = forwardRef<HTMLDivElement, HTMLAttributes<HTMLDivElement>>(
  ({ className, ...props }, ref) => (
    <div ref={ref} className={cn('card', className)} {...props} />
  ),
);
Card.displayName = 'Card';

export function CardHeader({ className, children, ...props }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div className={cn('px-5 pt-5 pb-3 flex items-center justify-between gap-4', className)} {...props}>
      {children}
    </div>
  );
}

export function CardTitle({ className, children, ...props }: HTMLAttributes<HTMLHeadingElement>) {
  return (
    <h3 className={cn('font-display text-lg leading-none tracking-tight', className)} {...props}>
      {children}
    </h3>
  );
}

export function CardBody({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('px-5 py-4', className)} {...props} />;
}

export function CardFooter({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn('px-5 py-3 border-t border-[var(--color-hairline)] bg-[var(--color-surface-2)]/50 rounded-b-[14px]', className)}
      {...props}
    />
  );
}

// ---------- Page header -----------------------------------------------
export function PageHeader({
  title, description, actions, className,
}: {
  title: string;
  description?: string;
  actions?: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={cn('mb-6 flex items-start justify-between gap-4 flex-wrap', className)}>
      <div>
        <h1 className="font-display text-[28px] leading-tight tracking-tight">{title}</h1>
        {description && (
          <p className="text-sm text-[var(--color-ink-muted)] mt-1">{description}</p>
        )}
      </div>
      {actions && <div className="flex items-center gap-2">{actions}</div>}
    </div>
  );
}

// ---------- Stat card -------------------------------------------------
export function StatCard({
  label, value, delta, tone = 'neutral', icon, className,
}: {
  label: string;
  value: string | number;
  delta?: string;
  tone?: 'neutral' | 'success' | 'warning' | 'danger' | 'info';
  icon?: React.ReactNode;
  className?: string;
}) {
  const toneColor = {
    neutral: 'text-[var(--color-ink-muted)]',
    success: 'text-[var(--color-emerald-500)]',
    warning: 'text-[var(--color-amber-500)]',
    danger:  'text-[var(--color-rose-500)]',
    info:    'text-[var(--color-teal-600)]',
  }[tone];

  return (
    <div className={cn('card p-5', className)}>
      <div className="flex items-start justify-between">
        <p className="text-xs uppercase tracking-widest text-[var(--color-ink-muted)] font-medium">
          {label}
        </p>
        {icon && <div className={cn('opacity-70', toneColor)}>{icon}</div>}
      </div>
      <p className="font-display text-[34px] leading-none tracking-tight mt-3 number">{value}</p>
      {delta && <p className={cn('text-xs mt-2', toneColor)}>{delta}</p>}
    </div>
  );
}

// ---------- Badge / pill ---------------------------------------------
export function Badge({
  tone = 'neutral', children, className,
}: {
  tone?: 'neutral' | 'info' | 'success' | 'warning' | 'danger';
  children: React.ReactNode;
  className?: string;
}) {
  const toneClass = {
    neutral: 'bg-[var(--color-surface-2)] text-[var(--color-ink-muted)] border-[var(--color-hairline)]',
    info:    'bg-[var(--color-teal-50)] text-[var(--color-teal-700)] border-[var(--color-teal-100)]',
    success: 'bg-emerald-50 text-emerald-700 border-emerald-100',
    warning: 'bg-amber-50 text-amber-700 border-amber-100',
    danger:  'bg-rose-50 text-rose-700 border-rose-100',
  }[tone];
  return (
    <span className={cn('pill border', toneClass, className)}>
      {children}
    </span>
  );
}

// ---------- Table shells ---------------------------------------------
export function Table({ className, ...props }: HTMLAttributes<HTMLTableElement>) {
  return (
    <div className="overflow-x-auto">
      <table className={cn('w-full text-sm', className)} {...props} />
    </div>
  );
}

export function Thead({ className, ...props }: HTMLAttributes<HTMLTableSectionElement>) {
  return (
    <thead
      className={cn(
        'text-left text-[11px] uppercase tracking-widest text-[var(--color-ink-muted)] border-b border-[var(--color-hairline)] font-medium',
        className,
      )}
      {...props}
    />
  );
}

export function Th({ className, ...props }: HTMLAttributes<HTMLTableCellElement>) {
  return <th className={cn('px-4 py-3', className)} {...props} />;
}

export function Tbody({ className, ...props }: HTMLAttributes<HTMLTableSectionElement>) {
  return <tbody className={cn('divide-y divide-[var(--color-hairline)]', className)} {...props} />;
}

export function Tr({ className, ...props }: HTMLAttributes<HTMLTableRowElement>) {
  return (
    <tr
      className={cn('hover:bg-[var(--color-surface-2)]/60 transition-colors', className)}
      {...props}
    />
  );
}

export function Td({ className, ...props }: HTMLAttributes<HTMLTableCellElement>) {
  return <td className={cn('px-4 py-3 align-middle', className)} {...props} />;
}

// ---------- Empty state ---------------------------------------------
export function EmptyState({
  title, description, action, icon,
}: {
  title: string;
  description?: string;
  action?: React.ReactNode;
  icon?: React.ReactNode;
}) {
  return (
    <div className="py-16 text-center">
      {icon && (
        <div className="w-12 h-12 mx-auto mb-4 rounded-full bg-[var(--color-teal-50)] text-[var(--color-teal-600)] grid place-items-center">
          {icon}
        </div>
      )}
      <h3 className="font-display text-lg mb-1.5">{title}</h3>
      {description && (
        <p className="text-sm text-[var(--color-ink-muted)] max-w-sm mx-auto mb-4">{description}</p>
      )}
      {action}
    </div>
  );
}

// ---------- Skeleton rows --------------------------------------------
export function SkeletonRows({ rows = 5, cols = 4 }: { rows?: number; cols?: number }) {
  return (
    <Tbody>
      {Array.from({ length: rows }).map((_, r) => (
        <Tr key={r}>
          {Array.from({ length: cols }).map((_, c) => (
            <Td key={c}>
              <div className="skeleton h-4 w-full" />
            </Td>
          ))}
        </Tr>
      ))}
    </Tbody>
  );
}
