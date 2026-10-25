'use client';

import { useState, useRef, useEffect } from 'react';
import { MessageCircle, X, Send, User, Bot } from 'lucide-react';
import { cn } from '@/lib/utils';

type Message = {
  id: string;
  role: 'user' | 'bot';
  text: string;
  time: Date;
};

const WELCOME: Message = {
  id: 'welcome',
  role: 'bot',
  text:
    'Hi! I\'m Nishat Assist. I can help you with: booking an appointment, finding a doctor by specialty, visiting hours, directions, emergency contacts, or connecting you with our front desk. What do you need today?',
  time: new Date(),
};

// Extremely simple rule-based responses — wire to a real LLM-backed endpoint
// in production (SRS §10 Integrations — AI Chatbot Engine).
function localReply(input: string): string {
  const q = input.toLowerCase();
  if (/book|appointment|schedule/.test(q))
    return 'You can book online at /welcome/book — choose a doctor, pick a slot, and you\'ll receive a confirmation within seconds. Want me to direct you there?';
  if (/emergency|urgent|ambulance/.test(q))
    return 'For emergencies, please call +91 98000 11111 immediately or come directly to our Emergency Department. We\'re open 24×7.';
  if (/hours|timing|open/.test(q))
    return 'OPD: Mon–Sat 9:00 AM – 1:00 PM and 4:00 PM – 7:00 PM. Emergency: 24×7. Sunday is for emergencies only.';
  if (/doctor|specialist|cardiolog|pediatric/.test(q))
    return 'You can browse all our doctors with specialties, languages, and availability at /welcome/doctors.';
  if (/visit|rules|guidelines/.test(q))
    return 'Visiting hours are 10 AM – 12 PM and 5 PM – 7 PM daily. ICU and private rooms are flexible — please coordinate with the nursing station.';
  if (/address|location|directions|how to reach/.test(q))
    return '123 Main Road, Hyderabad, Telangana 500001. If you\'d like, I can help with a map link.';
  if (/thank|thanks/.test(q))
    return 'You\'re welcome! Anything else I can help with?';
  return 'I\'m not sure about that one. I can connect you with our front desk — you can reach them at +91 98000 00000 during hospital hours.';
}

export function Chatbot() {
  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState<Message[]>([WELCOME]);
  const [input, setInput] = useState('');
  const [thinking, setThinking] = useState(false);
  const scrollRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight });
  }, [messages, open, thinking]);

  async function send() {
    if (!input.trim()) return;
    const userMsg: Message = {
      id: crypto.randomUUID(),
      role: 'user',
      text: input.trim(),
      time: new Date(),
    };
    setMessages((m) => [...m, userMsg]);
    setInput('');
    setThinking(true);

    // Simulated delay for feel. Swap for a real fetch to /api/v1/public/chatbot.
    setTimeout(() => {
      setMessages((m) => [
        ...m,
        {
          id: crypto.randomUUID(),
          role: 'bot',
          text: localReply(userMsg.text),
          time: new Date(),
        },
      ]);
      setThinking(false);
    }, 650);
  }

  return (
    <>
      {!open && (
        <button
          onClick={() => setOpen(true)}
          className="fixed bottom-6 right-6 z-50 h-14 w-14 rounded-full bg-[var(--color-teal-600)] hover:bg-[var(--color-teal-700)] text-white shadow-[0_10px_40px_-10px_rgba(0,85,102,0.5)] flex items-center justify-center transition-transform hover:scale-110"
          aria-label="Open chat assistant"
        >
          <MessageCircle className="h-6 w-6" />
          <span className="absolute -top-1 -right-1 h-3 w-3 bg-emerald-500 rounded-full border-2 border-white" />
        </button>
      )}

      {open && (
        <div className="fixed bottom-6 right-6 z-50 w-[380px] max-w-[calc(100vw-24px)] h-[560px] max-h-[calc(100vh-48px)] flex flex-col card shadow-[0_25px_70px_-15px_rgba(0,0,0,0.25)] overflow-hidden animate-in slide-in-from-bottom-4 enter">
          {/* Header */}
          <div className="bg-gradient-to-r from-[var(--color-teal-700)] to-[var(--color-teal-600)] text-white p-4 flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="h-9 w-9 rounded-full bg-white/15 grid place-items-center backdrop-blur-sm">
                <Bot className="h-4 w-4" />
              </div>
              <div>
                <p className="font-display text-base leading-none">Nishat Assist</p>
                <p className="text-xs text-white/70 flex items-center gap-1 mt-0.5">
                  <span className="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                  Online
                </p>
              </div>
            </div>
            <button
              onClick={() => setOpen(false)}
              className="p-2 hover:bg-white/10 rounded-md"
              aria-label="Close chat"
            >
              <X className="h-4 w-4" />
            </button>
          </div>

          {/* Messages */}
          <div ref={scrollRef} className="flex-1 overflow-y-auto px-4 py-4 space-y-3 bg-[var(--color-surface-2)]/50">
            {messages.map((m) => (
              <div
                key={m.id}
                className={cn(
                  'flex items-start gap-2 max-w-[85%]',
                  m.role === 'user' ? 'ml-auto flex-row-reverse' : 'mr-auto',
                )}
              >
                <div
                  className={cn(
                    'h-7 w-7 rounded-full grid place-items-center shrink-0',
                    m.role === 'user'
                      ? 'bg-[var(--color-teal-600)] text-white'
                      : 'bg-white border border-[var(--color-hairline)] text-[var(--color-teal-600)]',
                  )}
                >
                  {m.role === 'user' ? <User className="h-3 w-3" /> : <Bot className="h-3 w-3" />}
                </div>
                <div
                  className={cn(
                    'px-3 py-2 rounded-[12px] text-sm leading-relaxed',
                    m.role === 'user'
                      ? 'bg-[var(--color-teal-600)] text-white rounded-tr-sm'
                      : 'bg-white border border-[var(--color-hairline)] rounded-tl-sm',
                  )}
                >
                  {m.text}
                </div>
              </div>
            ))}
            {thinking && (
              <div className="flex items-center gap-2 mr-auto">
                <div className="h-7 w-7 rounded-full bg-white border border-[var(--color-hairline)] text-[var(--color-teal-600)] grid place-items-center">
                  <Bot className="h-3 w-3" />
                </div>
                <div className="bg-white border border-[var(--color-hairline)] rounded-[12px] px-3 py-2 text-sm flex gap-1">
                  <span className="h-1.5 w-1.5 bg-[var(--color-teal-600)] rounded-full animate-bounce" />
                  <span className="h-1.5 w-1.5 bg-[var(--color-teal-600)] rounded-full animate-bounce" style={{ animationDelay: '0.1s' }} />
                  <span className="h-1.5 w-1.5 bg-[var(--color-teal-600)] rounded-full animate-bounce" style={{ animationDelay: '0.2s' }} />
                </div>
              </div>
            )}
          </div>

          {/* Input */}
          <form
            className="p-3 border-t border-[var(--color-hairline)] bg-white flex gap-2"
            onSubmit={(e) => { e.preventDefault(); send(); }}
          >
            <input
              value={input}
              onChange={(e) => setInput(e.target.value)}
              placeholder="Type a message…"
              className="flex-1 h-10 px-3 rounded-[10px] border border-[var(--color-hairline)] bg-[var(--color-surface-2)] text-sm focus:outline-none focus:border-[var(--color-teal-500)] focus:bg-white"
            />
            <button
              type="submit"
              disabled={!input.trim()}
              className="h-10 w-10 bg-[var(--color-teal-600)] hover:bg-[var(--color-teal-700)] text-white rounded-[10px] disabled:opacity-50 grid place-items-center transition-colors"
            >
              <Send className="h-4 w-4" />
            </button>
          </form>
        </div>
      )}
    </>
  );
}
