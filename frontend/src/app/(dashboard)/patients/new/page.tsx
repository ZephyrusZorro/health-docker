'use client';

import { useRouter } from 'next/navigation';
import { useState, type FormEvent } from 'react';
import { useMutation } from '@tanstack/react-query';
import { toast } from 'sonner';
import { ArrowLeft, UserPlus } from 'lucide-react';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Field, Input, Select, Textarea } from '@/components/ui/field';
import { Card, CardBody, CardHeader, CardTitle, PageHeader } from '@/components/ui/surface';
import { patientsApi } from '@/lib/api/endpoints';
import { useAuth } from '@/lib/auth/context';

export default function NewPatientPage() {
  const router = useRouter();
  const { user } = useAuth();

  const [form, setForm] = useState({
    first_name: '',
    last_name: '',
    gender: 'male' as const,
    date_of_birth: '',
    blood_group: '',
    marital_status: '',
    occupation: '',
    phone: '',
    phone_alt: '',
    email: '',
    address_line1: '',
    city: '',
    state: '',
    pincode: '',
    emergency_name: '',
    emergency_phone: '',
    emergency_relationship: '',
    payer_type: 'self' as const,
    insurance_provider: '',
    insurance_number: '',
    referred_by: '',
    notes: '',
  });

  function update<K extends keyof typeof form>(key: K, value: typeof form[K]) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  const mutation = useMutation({
    mutationFn: () => patientsApi.create({
      facility_id: user?.facility?.id as number,
      first_name: form.first_name,
      last_name: form.last_name || undefined,
      gender: form.gender,
      date_of_birth: form.date_of_birth,
      blood_group: form.blood_group || undefined,
      marital_status: form.marital_status || undefined,
      occupation: form.occupation || undefined,
      phone: form.phone || undefined,
      phone_alt: form.phone_alt || undefined,
      email: form.email || undefined,
      address: form.address_line1 ? {
        line1: form.address_line1,
        city: form.city,
        state: form.state,
        pincode: form.pincode,
      } : undefined,
      emergency_contact: form.emergency_name ? {
        name: form.emergency_name,
        phone: form.emergency_phone,
        relationship: form.emergency_relationship,
      } : undefined,
      payer_type: form.payer_type,
      insurance_provider: form.insurance_provider || undefined,
      insurance_number: form.insurance_number || undefined,
      referred_by: form.referred_by || undefined,
      notes: form.notes || undefined,
    } as any),
    onSuccess: (patient) => {
      toast.success(`Patient registered. UHID: ${patient.uhid}`);
      router.push(`/patients/${patient.id}` as any);
    },
    onError: (err: any) => {
      const msg = err.response?.data?.message ?? 'Registration failed';
      toast.error(msg);
    },
  });

  function onSubmit(e: FormEvent) {
    e.preventDefault();
    mutation.mutate();
  }

  return (
    <div className="max-w-4xl">
      <Link href="/patients" className="text-sm text-[var(--color-ink-muted)] hover:text-[var(--color-ink)] inline-flex items-center gap-1 mb-3">
        <ArrowLeft className="h-3.5 w-3.5" />
        Back to patients
      </Link>

      <PageHeader
        title="Register a new patient"
        description="A Unique Hospital ID (UHID) is generated automatically and is permanent."
      />

      <form onSubmit={onSubmit} className="space-y-6">
        {/* Demographics */}
        <Card className="enter">
          <CardHeader><CardTitle>Demographics</CardTitle></CardHeader>
          <CardBody className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="First name" required>
              <Input required value={form.first_name} onChange={(e) => update('first_name', e.target.value)} />
            </Field>
            <Field label="Last name">
              <Input value={form.last_name} onChange={(e) => update('last_name', e.target.value)} />
            </Field>
            <Field label="Gender" required>
              <Select required value={form.gender} onChange={(e) => update('gender', e.target.value as any)}>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
                <option value="unknown">Unknown</option>
              </Select>
            </Field>
            <Field label="Date of birth" required>
              <Input type="date" required value={form.date_of_birth} onChange={(e) => update('date_of_birth', e.target.value)} />
            </Field>
            <Field label="Blood group">
              <Select value={form.blood_group} onChange={(e) => update('blood_group', e.target.value)}>
                <option value="">—</option>
                {['A+','A-','B+','B-','AB+','AB-','O+','O-'].map((bg) => (
                  <option key={bg} value={bg}>{bg}</option>
                ))}
              </Select>
            </Field>
            <Field label="Marital status">
              <Select value={form.marital_status} onChange={(e) => update('marital_status', e.target.value)}>
                <option value="">—</option>
                <option value="single">Single</option>
                <option value="married">Married</option>
                <option value="divorced">Divorced</option>
                <option value="widowed">Widowed</option>
              </Select>
            </Field>
            <Field label="Occupation" className="md:col-span-2">
              <Input value={form.occupation} onChange={(e) => update('occupation', e.target.value)} />
            </Field>
          </CardBody>
        </Card>

        {/* Contact */}
        <Card className="enter enter-1">
          <CardHeader><CardTitle>Contact</CardTitle></CardHeader>
          <CardBody className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Field label="Phone" required>
              <Input required value={form.phone} onChange={(e) => update('phone', e.target.value)} placeholder="+91 98XXX XXXXX" />
            </Field>
            <Field label="Alternate phone">
              <Input value={form.phone_alt} onChange={(e) => update('phone_alt', e.target.value)} />
            </Field>
            <Field label="Email" className="md:col-span-2">
              <Input type="email" value={form.email} onChange={(e) => update('email', e.target.value)} />
            </Field>
            <Field label="Address line" className="md:col-span-2">
              <Input value={form.address_line1} onChange={(e) => update('address_line1', e.target.value)} />
            </Field>
            <Field label="City">
              <Input value={form.city} onChange={(e) => update('city', e.target.value)} />
            </Field>
            <Field label="State">
              <Input value={form.state} onChange={(e) => update('state', e.target.value)} />
            </Field>
            <Field label="Pincode">
              <Input value={form.pincode} onChange={(e) => update('pincode', e.target.value)} />
            </Field>
          </CardBody>
        </Card>

        {/* Emergency + Payer */}
        <div className="grid md:grid-cols-2 gap-6">
          <Card className="enter enter-2">
            <CardHeader><CardTitle>Emergency contact</CardTitle></CardHeader>
            <CardBody className="space-y-4">
              <Field label="Name">
                <Input value={form.emergency_name} onChange={(e) => update('emergency_name', e.target.value)} />
              </Field>
              <Field label="Phone">
                <Input value={form.emergency_phone} onChange={(e) => update('emergency_phone', e.target.value)} />
              </Field>
              <Field label="Relationship">
                <Select value={form.emergency_relationship} onChange={(e) => update('emergency_relationship', e.target.value)}>
                  <option value="">—</option>
                  <option value="Spouse">Spouse</option>
                  <option value="Parent">Parent</option>
                  <option value="Child">Child</option>
                  <option value="Sibling">Sibling</option>
                  <option value="Other">Other</option>
                </Select>
              </Field>
            </CardBody>
          </Card>

          <Card className="enter enter-3">
            <CardHeader><CardTitle>Payer & referral</CardTitle></CardHeader>
            <CardBody className="space-y-4">
              <Field label="Payer type" required>
                <Select required value={form.payer_type} onChange={(e) => update('payer_type', e.target.value as any)}>
                  <option value="self">Self-pay</option>
                  <option value="insurance">Insurance</option>
                  <option value="corporate">Corporate</option>
                  <option value="government">Government</option>
                  <option value="charity">Charity</option>
                </Select>
              </Field>
              {form.payer_type === 'insurance' && (
                <>
                  <Field label="Insurance provider">
                    <Input value={form.insurance_provider} onChange={(e) => update('insurance_provider', e.target.value)} />
                  </Field>
                  <Field label="Insurance number">
                    <Input value={form.insurance_number} onChange={(e) => update('insurance_number', e.target.value)} />
                  </Field>
                </>
              )}
              <Field label="Referred by">
                <Input value={form.referred_by} onChange={(e) => update('referred_by', e.target.value)} />
              </Field>
            </CardBody>
          </Card>
        </div>

        {/* Notes */}
        <Card className="enter enter-4">
          <CardHeader><CardTitle>Additional notes</CardTitle></CardHeader>
          <CardBody>
            <Textarea
              value={form.notes}
              onChange={(e) => update('notes', e.target.value)}
              placeholder="Known allergies, chronic conditions, VIP flags…"
              rows={4}
            />
          </CardBody>
        </Card>

        {/* Actions */}
        <div className="flex justify-end gap-3 sticky bottom-0 py-4">
          <Link href="/patients">
            <Button type="button" variant="secondary">Cancel</Button>
          </Link>
          <Button type="submit" loading={mutation.isPending} size="lg">
            <UserPlus className="h-4 w-4" />
            Register patient
          </Button>
        </div>
      </form>
    </div>
  );
}
