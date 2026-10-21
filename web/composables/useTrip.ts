/** Empty trip form object (shared by new + edit pages). */
export function blankTrip() {
  return {
    name: '', code: '', type: 'umrah', destination: '',
    transport: '', airline: '', flight_no: '', boarding_point: '',
    hotel_makkah: '', nights_makkah: '', hotel_madinah: '', nights_madinah: '',
    supervisor: '', supervisor_phone: '',
    departure_date: new Date().toISOString().slice(0, 10), return_date: '',
    capacity: 40, price: 0, package_id: '', status: 'scheduled', note: '',
  }
}

/** Map an API trip record into a form object (nulls → ''). */
export function tripToForm(t: any) {
  const form: any = blankTrip()
  for (const k of Object.keys(form)) form[k] = t[k] ?? ''
  form.type = t.type || 'umrah'
  form.status = t.status || 'scheduled'
  form.capacity = t.capacity ?? 0
  form.price = t.price != null ? +t.price : 0
  form.package_id = t.package_id || ''
  return form
}

/** Payload with empty strings normalized to null for the API. */
export function tripPayload(form: any) {
  return {
    ...form,
    package_id: form.package_id || null,
    return_date: form.return_date || null,
    transport: form.transport || null,
    nights_makkah: form.nights_makkah === '' ? null : form.nights_makkah,
    nights_madinah: form.nights_madinah === '' ? null : form.nights_madinah,
  }
}
