/** Booking form helpers + labels (shared by new / edit / list / calendar). */
export const bookingStatus: Record<string, { label: string; cls: string }> = {
  pending: { label: 'معلّق', cls: 'warn' }, confirmed: { label: 'مؤكد', cls: 'ok' }, completed: { label: 'مكتمل', cls: 'info' }, cancelled: { label: 'ملغى', cls: 'bad' },
}
export const depositStatus: Record<string, string> = { pending: 'لم يُستلم', held: 'محتجز', returned: 'مُرجع', forfeited: 'مُصادر' }
export const periodLabel: Record<string, string> = { day: 'يوم كامل', night: 'ليلة', hour: 'ساعة' }
export const bookingSource: Record<string, string> = { walk_in: 'حضور مباشر', phone: 'اتصال', whatsapp: 'واتساب', online: 'إلكتروني', agent: 'وكيل / وسيط' }
export const paymentKind: Record<string, string> = { payment: 'دفعة', deposit: 'استلام تأمين', refund: 'استرداد', deposit_return: 'إرجاع تأمين' }

export const blankBooking = (date?: string) => ({
  unit_id: '', customer_id: '', customer_name: '', customer_phone: '', customer_email: '',
  period: 'day', start_at: date ? `${date}T10:00` : '', end_at: date ? `${date}T23:00` : '',
  qty: 1, unit_price: 0, discount: 0, deposit: 0,
  event_type: '', source: '', guests: '', adults: '', children: '',
  note: '', special_requests: '', internal_note: '', status: 'pending',
  lines: [] as any[],
})

const toLocal = (v: any) => (v ? String(v).slice(0, 16).replace(' ', 'T') : '')

export const bookingToForm = (b: any) => ({
  ...blankBooking(),
  unit_id: b.unit_id || '', customer_id: b.customer_id || '', customer_name: b.customer_name || '', customer_phone: b.customer_phone || '', customer_email: b.customer_email || '',
  period: b.period || 'day', start_at: toLocal(b.start_at), end_at: toLocal(b.end_at),
  qty: +b.qty || 1, unit_price: +b.unit_price || 0, discount: +b.discount || 0, deposit: +b.deposit || 0,
  event_type: b.event_type || '', source: b.source || '', guests: b.guests ?? '', adults: b.adults ?? '', children: b.children ?? '',
  note: b.note || '', special_requests: b.special_requests || '', internal_note: b.internal_note || '', status: b.status === 'confirmed' ? 'confirmed' : 'pending',
  lines: (b.lines || []).map((l: any) => ({ service_id: l.service_id, name: l.name, qty: +l.qty, price: +l.price })),
})

export const bookingPayload = (form: any) => ({
  ...form,
  customer_id: form.customer_id || null, source: form.source || null,
  guests: form.guests === '' ? null : +form.guests, adults: form.adults === '' ? null : +form.adults, children: form.children === '' ? null : +form.children,
  qty: +form.qty || 1, unit_price: +form.unit_price || 0, discount: +form.discount || 0, deposit: +form.deposit || 0,
  lines: form.lines.map((l: any) => ({ service_id: l.service_id || null, name: l.name, qty: +l.qty, price: +l.price })),
})

export const calcBooking = (form: any, taxRate: number) => {
  const r3 = (n: number) => Math.round(n * 1000) / 1000
  const services = r3(form.lines.reduce((s: number, l: any) => s + (+l.qty || 0) * (+l.price || 0), 0))
  const base = r3((+form.qty || 0) * (+form.unit_price || 0) + services)
  const taxable = Math.max(0, base - (+form.discount || 0))
  const tax = r3(taxable * (taxRate || 0) / 100)
  return { services, base, taxable, tax, total: r3(taxable + tax) }
}
