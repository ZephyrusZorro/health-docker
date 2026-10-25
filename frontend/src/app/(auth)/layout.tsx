export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen grid lg:grid-cols-[1fr_520px]">
      {/* Left panel — brand / imagery */}
      <div className="hidden lg:flex flex-col justify-between p-14 relative overflow-hidden bg-gradient-to-br from-[var(--color-teal-900)] via-[var(--color-teal-700)] to-[var(--color-teal-600)]">
        <div
          className="absolute inset-0 opacity-[0.08] mix-blend-overlay"
          style={{
            backgroundImage:
              'radial-gradient(circle at 30% 20%, white 2px, transparent 2px), radial-gradient(circle at 70% 60%, white 1px, transparent 1px)',
            backgroundSize: '48px 48px, 32px 32px',
          }}
          aria-hidden
        />
        <div className="relative z-10">
          <div className="flex items-center gap-3 text-white">
            <svg viewBox="0 0 32 32" className="h-9 w-9" aria-hidden>
              <circle cx="16" cy="16" r="15" fill="none" stroke="currentColor" strokeWidth="1.5" />
              <path d="M16 8v16M8 16h16" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" />
            </svg>
            <span className="font-display text-2xl tracking-tight">Nishat HMS</span>
          </div>
        </div>

        <div className="relative z-10 text-white max-w-lg">
          <h1 className="font-display text-[44px] leading-[1.05] tracking-tight mb-5">
            Care, coordinated.
          </h1>
          <p className="text-white/80 leading-relaxed text-[15px]">
            An end-to-end hospital management platform — from the front desk to the ward,
            from the pharmacy to the lab. Built for the teams who keep patients well.
          </p>
          <div className="mt-10 flex flex-wrap gap-2">
            {['OPD', 'IPD', 'EMR', 'Pharmacy', 'Laboratory', 'Billing'].map((m) => (
              <span
                key={m}
                className="px-3 py-1 rounded-full text-xs font-medium bg-white/10 backdrop-blur-sm border border-white/15"
              >
                {m}
              </span>
            ))}
          </div>
        </div>

        <p className="relative z-10 text-xs text-white/50">
          © {new Date().getFullYear()} Nishat Nursing Home · v1.0
        </p>
      </div>

      {/* Right panel — auth form */}
      <div className="flex items-center justify-center p-6 lg:p-12">
        <div className="w-full max-w-md">{children}</div>
      </div>
    </div>
  );
}
