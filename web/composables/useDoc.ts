/** Shared helpers for CRM documents (invoices + quotes): form shape, payload, client-side totals, labels. */
export type DocKind = 'invoice' | 'quote'

export const invoiceStatus: Record<string, { label: string; cls: string }> = {
  draft: L('مسودّة', 'Draft', { cls: 'muted' }), sent: L('مرسلة', 'Sent', { cls: 'info' }), partial: L('مسدّدة جزئياً', 'Partial', { cls: 'warn' }),
  paid: L('مسدّدة', 'Paid', { cls: 'ok' }), cancelled: L('ملغاة', 'Cancelled', { cls: 'bad' }),
}
export const quoteStatus: Record<string, { label: string; cls: string }> = {
  draft: L('مسودّة', 'Draft', { cls: 'muted' }), sent: L('مُرسَل', 'Sent', { cls: 'info' }), accepted: L('مقبول', 'Accepted', { cls: 'ok' }),
  rejected: L('مرفوض', 'Rejected', { cls: 'bad' }), expired: L('منتهٍ', 'Expired', { cls: 'warn' }), converted: L('محوّل لفاتورة', 'Converted', { cls: 'ok' }),
}
export const payMethods: Record<string, any> = { cash: L('نقدي', 'Cash'), card: L('بطاقة', 'Card'), transfer: L('تحويل بنكي', 'Bank transfer'), cheque: L('شيك', 'Cheque') }

export const blankLine = () => ({
  item_id: '', description: '', qty: 1, unit: '', unit_price: 0, discount: 0,
})

export const blankDoc = (kind: DocKind, taxRate = 0) => {
  return {
    customer_id: '', date: new Date().toISOString().slice(0, 10), status: 'draft',
    due_date: '', payment_terms_days: '', valid_until: '',
    subject: '', reference: '',
    discount_type: 'amount', discount_value: 0, tax_rate: taxRate,
    terms: '', notes: '', internal_notes: '',
    lines: [blankLine()],
  }
}

export function docToForm(kind: DocKind, d: any) {
  const f: any = blankDoc(kind, +d.tax_rate || 0)
  Object.assign(f, {
    customer_id: d.customer_id || '', date: d.date || f.date, status: d.status === 'sent' ? 'sent' : 'draft',
    due_date: d.due_date || '', payment_terms_days: d.payment_terms_days ?? '', valid_until: d.valid_until || '',
    subject: d.subject || '', reference: d.reference || '',
    discount_type: d.discount_type || 'amount', discount_value: +d.discount_value || 0, tax_rate: +d.tax_rate || 0,
    terms: d.terms || '', notes: d.notes || '', internal_notes: d.internal_notes || '',
    lines: (d.items || []).map((l: any) => ({ item_id: l.item_id || '', description: l.description || '', qty: +l.qty, unit: l.unit || '', unit_price: +l.unit_price, discount: +l.discount || 0 })),
  })
  if (!f.lines.length) f.lines = [blankLine()]
  return f
}

export function docPayload(form: any) {
  const lines = form.lines
    .filter((l: any) => (l.description && l.description.trim()) || l.item_id)
    .map((l: any) => ({ item_id: l.item_id || null, description: l.description || null, qty: +l.qty || 0, unit: l.unit || null, unit_price: +l.unit_price || 0, discount: +l.discount || 0 }))
  return {
    ...form,
    customer_id: form.customer_id || null,
    due_date: form.due_date || null, valid_until: form.valid_until || null,
    payment_terms_days: form.payment_terms_days === '' ? null : +form.payment_terms_days,
    discount_value: +form.discount_value || 0, tax_rate: +form.tax_rate || 0,
    lines,
  }
}

/** Mirrors App\Services\DocumentCalculator so the form shows the same totals the server will store. */
export function calcDoc(form: any) {
  const r3 = (n: number) => Math.round(n * 1000) / 1000
  const lines = form.lines.map((l: any) => ({ ...l, total: r3(Math.max(0, (+l.qty || 0) * (+l.unit_price || 0) - (+l.discount || 0))) }))
  const subtotal = r3(lines.reduce((s: number, l: any) => s + l.total, 0))
  const dv = +form.discount_value || 0
  const discount = form.discount_type === 'percent' ? r3(subtotal * Math.min(100, Math.max(0, dv)) / 100) : r3(Math.min(subtotal, Math.max(0, dv)))
  const taxable = Math.max(0, subtotal - discount)
  const tax = r3(taxable * (+form.tax_rate || 0) / 100)
  const total = r3(taxable + tax)
  return { lines, subtotal, discount, tax, total }
}

/** Days from today to a date (negative = past). */
export function daysUntil(date?: string | null): number | null {
  if (!date) return null
  const a = new Date(date + 'T00:00:00'); const b = new Date(); b.setHours(0, 0, 0, 0)
  return Math.round((a.getTime() - b.getTime()) / 86400000)
}
