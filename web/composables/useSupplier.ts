/** Supplier form helpers (shared by new + edit pages). */
export const blankSupplier = () => ({
  name: '', supplier_type: 'company', code: '', contact_person: '',
  phone: '', phone2: '', whatsapp: '', email: '',
  tax_number: '', cr_number: '',
  address: '', country: '', city: '', area: '',
  bank_details: '', payment_terms_days: '', category: '', opening_balance: 0, tags: '',
  notes: '', is_active: true,
})

export const supplierToForm = (s: any) => {
  const f: any = blankSupplier()
  for (const k of Object.keys(f)) f[k] = s[k] ?? (typeof f[k] === 'boolean' ? false : '')
  f.is_active = !!s.is_active; f.supplier_type = s.supplier_type || 'company'; f.opening_balance = +s.opening_balance || 0
  return f
}

export const supplierPayload = (form: any) => ({
  ...form,
  payment_terms_days: form.payment_terms_days === '' ? null : +form.payment_terms_days,
  opening_balance: +form.opening_balance || 0,
})
