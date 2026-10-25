import Link from 'next/link';

export default function PublicLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen bg-[var(--color-canvas)]">
      <header className="bg-white/90 backdrop-blur-sm border-b border-[var(--color-hairline)] sticky top-0 z-30">
        <div className="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
          <Link href="/welcome" className="flex items-center gap-2.5">
            <svg viewBox="0 0 32 32" className="h-7 w-7 text-[var(--color-teal-600)]">
              <circle cx="16" cy="16" r="15" fill="none" stroke="currentColor" strokeWidth="1.5" />
              <path d="M16 8v16M8 16h16" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" />
            </svg>
            <span className="font-display text-lg tracking-tight">Nishat Nursing Home</span>
          </Link>

          <nav className="hidden md:flex items-center gap-8 text-sm">
            <Link href="/welcome" className="hover:text-[var(--color-teal-700)] transition-colors">Home</Link>
            <Link href="/welcome/doctors" className="hover:text-[var(--color-teal-700)] transition-colors">Doctors</Link>
            <Link href="/welcome/departments" className="hover:text-[var(--color-teal-700)] transition-colors">Departments</Link>
            <Link href="/welcome/book" className="hover:text-[var(--color-teal-700)] transition-colors">Book online</Link>
            <Link href="/welcome/contact" className="hover:text-[var(--color-teal-700)] transition-colors">Contact</Link>
          </nav>

          <Link
            href="/login"
            className="text-sm font-medium text-[var(--color-teal-700)] hover:text-[var(--color-teal-900)]"
          >
            Staff login →
          </Link>
        </div>
      </header>

      <main>{children}</main>

      <footer className="border-t border-[var(--color-hairline)] bg-white mt-20">
        <div className="max-w-6xl mx-auto px-6 py-10 grid md:grid-cols-3 gap-8 text-sm">
          <div>
            <h4 className="font-display text-lg mb-3">Nishat Nursing Home</h4>
            <p className="text-[var(--color-ink-muted)] leading-relaxed">
              Modern, patient-centred care for the community — over two decades of service.
            </p>
          </div>
          <div>
            <h4 className="font-medium mb-3">Visit us</h4>
            <p className="text-[var(--color-ink-muted)]">
              123 Main Road<br />
              Hyderabad, Telangana 500001
            </p>
          </div>
          <div>
            <h4 className="font-medium mb-3">Get in touch</h4>
            <p className="text-[var(--color-ink-muted)]">
              <span className="mono">+91 98000 00000</span><br />
              info@nishatnursinghome.com<br />
              <span className="text-[var(--color-rose-500)] font-medium">Emergency · <span className="mono">+91 98000 11111</span></span>
            </p>
          </div>
        </div>
        <div className="border-t border-[var(--color-hairline)] py-4 text-center text-xs text-[var(--color-ink-muted)]">
          © {new Date().getFullYear()} Nishat Nursing Home — All rights reserved.
        </div>
      </footer>
    </div>
  );
}
