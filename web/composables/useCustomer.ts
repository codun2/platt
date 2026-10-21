/** Empty customer form object (shared by new + edit pages). */
export function blankCustomer() {
  return {
    name: '', customer_type: 'individual', code: '',
    phone: '', phone2: '', whatsapp: '', email: '',
    tax_number: '', id_type: '', id_number: '', nationality: '', birth_date: '', gender: '',
    company_name: '', contact_person: '',
    address: '', country: '', city: '', area: '',
    category: '', credit_limit: '', opening_balance: '', payment_terms_days: '', tags: '',
    notes: '', is_active: true,
  }
}

/** Map an API customer record into a form object (nulls → ''). */
export function customerToForm(c: any) {
  const f: any = blankCustomer()
  for (const k of Object.keys(f)) f[k] = c[k] ?? (typeof f[k] === 'boolean' ? false : '')
  f.is_active = !!c.is_active
  f.customer_type = c.customer_type || 'individual'
  return f
}
