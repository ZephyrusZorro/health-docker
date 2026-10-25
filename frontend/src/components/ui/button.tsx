'use client';

import { cva, type VariantProps } from 'class-variance-authority';
import { forwardRef, type ButtonHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const button = cva(
  'inline-flex items-center justify-center gap-2 rounded-[10px] font-medium transition-all ' +
    'focus-visible:outline-2 focus-visible:outline-[var(--color-teal-500)] focus-visible:outline-offset-2 ' +
    'disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap',
  {
    variants: {
      variant: {
        primary:   'bg-[var(--color-teal-600)] text-white hover:bg-[var(--color-teal-700)] shadow-sm',
        secondary: 'bg-[var(--color-surface-2)] text-[var(--color-ink)] border border-[var(--color-hairline)] hover:bg-white',
        ghost:     'text-[var(--color-ink)] hover:bg-[var(--color-surface-2)]',
        danger:    'bg-[var(--color-rose-500)] text-white hover:brightness-110',
        outline:   'border border-[var(--color-teal-600)] text-[var(--color-teal-700)] hover:bg-[var(--color-teal-50)]',
        link:      'text-[var(--color-teal-700)] hover:underline underline-offset-4 p-0 rounded-none',
      },
      size: {
        xs: 'h-7 px-2.5 text-xs',
        sm: 'h-8 px-3 text-sm',
        md: 'h-10 px-4 text-sm',
        lg: 'h-11 px-6 text-base',
        icon: 'h-9 w-9 p-0',
      },
    },
    defaultVariants: { variant: 'primary', size: 'md' },
  },
);

export interface ButtonProps
  extends ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof button> {
  loading?: boolean;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, loading, disabled, children, ...props }, ref) => (
    <button
      ref={ref}
      className={cn(button({ variant, size, className }))}
      disabled={disabled || loading}
      {...props}
    >
      {loading && (
        <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="3" strokeDasharray="40 60" strokeLinecap="round" />
        </svg>
      )}
      {children}
    </button>
  ),
);
Button.displayName = 'Button';
