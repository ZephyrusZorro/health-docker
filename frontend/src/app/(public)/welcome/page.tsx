import Link from 'next/link';
import { Calendar, Phone, Clock, Stethoscope, HeartPulse, Baby, Activity, Bone, Ear } from 'lucide-react';
import { Chatbot } from '@/components/public/chatbot';

const SPECIALTIES = [
  { icon: HeartPulse, name: 'Cardiology',    desc: 'Heart health & interventional care' },
  { icon: Stethoscope,name: 'General Medicine', desc: 'Preventive & chronic care' },
  { icon: Baby,        name: 'Pediatrics',    desc: 'Neonatal to adolescent' },
  { icon: Bone,        name: 'Orthopedics',   desc: 'Joints, bones, sports injury' },
  { icon: Ear,         name: 'ENT',           desc: 'Ear, nose, throat' },
  { icon: Activity,    name: 'Emergency',     desc: '24×7 urgent care' },
];

export default function PublicHome() {
  return (
    <div>
      {/* Hero ------------------------------------------------------ */}
      <section className="relative overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-br from-[var(--color-teal-900)] via-[var(--color-teal-700)] to-[var(--color-teal-600)]" />
        <div
          className="absolute inset-0 opacity-10 mix-blend-overlay"
          style={{
            backgroundImage:
              'radial-gradient(circle at 30% 20%, white 2px, transparent 2px), radial-gradient(circle at 70% 60%, white 1px, transparent 1px)',
            backgroundSize: '48px 48px, 32px 32px',
          }}
        />
        <div className="relative max-w-6xl mx-auto px-6 py-24 md:py-32 text-white">
          <div className="max-w-2xl">
            <p className="inline-flex items-center gap-2 text-xs uppercase tracking-widest bg-white/10 backdrop-blur-sm border border-white/15 rounded-full px-3 py-1 mb-6">
              <span className="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse" />
              Open today · 8:00 AM – 10:00 PM
            </p>
            <h1 className="font-display text-5xl md:text-[64px] leading-[1.02] tracking-tight mb-6">
              Care, coordinated.
            </h1>
            <p className="text-lg text-white/80 leading-relaxed mb-8 max-w-xl">
              From routine consultations to complex inpatient care — a modern hospital experience
              built around you. Book an appointment online or walk in anytime.
            </p>
            <div className="flex gap-3 flex-wrap">
              <Link
                href="/welcome/book"
                className="h-12 px-6 bg-white text-[var(--color-teal-900)] rounded-[10px] font-medium hover:bg-white/90 transition-colors flex items-center gap-2"
              >
                <Calendar className="h-4 w-4" />
                Book appointment
              </Link>
              <a
                href="tel:+919800011111"
                className="h-12 px-6 border border-white/25 rounded-[10px] font-medium hover:bg-white/10 transition-colors flex items-center gap-2"
              >
                <Phone className="h-4 w-4" />
                Call emergency
              </a>
            </div>
          </div>
        </div>
      </section>

      {/* Quick facts ---------------------------------------------- */}
      <section className="max-w-6xl mx-auto px-6 py-16">
        <div className="grid md:grid-cols-3 gap-6">
          {[
            { big: '25+', small: 'Years of care' },
            { big: '60+', small: 'Specialists and staff' },
            { big: '24×7', small: 'Emergency services' },
          ].map((s, i) => (
            <div key={i} className="card p-8 text-center">
              <p className="font-display text-5xl text-[var(--color-teal-700)] tracking-tight">{s.big}</p>
              <p className="text-sm text-[var(--color-ink-muted)] mt-2 uppercase tracking-widest">{s.small}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Specialties --------------------------------------------- */}
      <section className="max-w-6xl mx-auto px-6 py-16">
        <div className="text-center mb-12">
          <p className="text-xs uppercase tracking-widest text-[var(--color-teal-600)] font-medium mb-2">
            Departments
          </p>
          <h2 className="font-display text-4xl tracking-tight">What we care for</h2>
        </div>
        <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {SPECIALTIES.map((s) => (
            <div key={s.name} className="card p-6 card-hover">
              <div className="h-11 w-11 rounded-[10px] bg-[var(--color-teal-50)] text-[var(--color-teal-700)] grid place-items-center mb-4">
                <s.icon className="h-5 w-5" />
              </div>
              <h3 className="font-display text-xl mb-1">{s.name}</h3>
              <p className="text-sm text-[var(--color-ink-muted)]">{s.desc}</p>
            </div>
          ))}
        </div>
      </section>

      {/* CTA ----------------------------------------------------- */}
      <section className="max-w-6xl mx-auto px-6 py-16">
        <div className="card p-10 md:p-16 text-center bg-gradient-to-br from-[var(--color-teal-50)] to-white">
          <Clock className="h-10 w-10 text-[var(--color-teal-600)] mx-auto mb-5" />
          <h2 className="font-display text-4xl tracking-tight mb-4">
            Skip the waiting room.
          </h2>
          <p className="text-[var(--color-ink-muted)] mb-7 max-w-lg mx-auto">
            Book an appointment online in under a minute. Your token is generated instantly,
            and you get SMS + email reminders.
          </p>
          <Link
            href="/welcome/book"
            className="inline-flex items-center gap-2 h-12 px-8 bg-[var(--color-teal-600)] hover:bg-[var(--color-teal-700)] text-white rounded-[10px] font-medium transition-colors"
          >
            Book online now
          </Link>
        </div>
      </section>

      <Chatbot />
    </div>
  );
}
