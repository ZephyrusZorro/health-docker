'use client';

import { forwardRef, type InputHTMLAttributes, type LabelHTMLAttributes, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const fieldBase =
  'w-full h-10 rounded-[10px] border border-[var(--color-hairline)] bg-white px-3 ' +
  'text-sm text-[var(--color-ink)] placeholder:text-[var(--color-ink-muted)] ' +
  'focus:border-[var(--color-teal-500)] focus:ring-2 focus:ring-[var(--color-teal-100)] focus:outline-none ' +
  'transition-[border,box-shadow] duration-100 ' +
  'disabled:opacity-50 disabled:cursor-not-allowed';

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(
  ({ className, ...props }, ref) => (
    <input ref={ref} className={cn(fieldBase, className)} {...props} />
  ),
);
Input.displayName = 'Input';

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement>>(
  ({ className, ...props }, ref) => (
    <textarea ref={ref} className={cn(fieldBase, 'h-auto py-2 min-h-[80px]', className)} {...props} />
  ),
);
Textarea.displayName = 'Textarea';

export const Select = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement>>(
  ({ className, children, ...props }, ref) => (
    <select ref={ref} className={cn(fieldBase, 'pr-9 appearance-none bg-[url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2712%27 height=%2712%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%23737380%27 stroke-width=%272%27%3E%3Cpolyline points=%276 9 12 15 18 9%27/%3E%3C/svg%3E")] bg-no-repeat bg-[right_12px_center]', className)} {...props}>
      {children}
    </select>
  ),
);
Select.displayName = 'Select';

export const Label = forwardRef<HTMLLabelElement, LabelHTMLAttributes<HTMLLabelElement>>(
  ({ className, children, ...props }, ref) => (
    <label
      ref={ref}
      className={cn(
        'text-xs font-medium uppercase tracking-wide text-[var(--color-ink-muted)] mb-1.5 block',
        className,
      )}
      {...props}
    >
      {children}
    </label>
  ),
);
Label.displayName = 'Label';

/** A complete labelled field. */
export function Field({
  label, error, required, hint, children, className,
}: {
  label?: string;
  error?: string;
  required?: boolean;
  hint?: string;
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={cn('flex flex-col', className)}>
      {label && (
        <Label>
          {label}
          {required && <span className="text-[var(--color-rose-500)] ml-1">*</span>}
        </Label>
      )}
      {children}
      {hint && !error && <p className="text-xs text-[var(--color-ink-muted)] mt-1">{hint}</p>}
      {error && <p className="text-xs text-[var(--color-rose-500)] mt-1">{error}</p>}
    </div>
  );
}
