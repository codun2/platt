/** Rental engine UI helpers: labels, sector-aware wording, form shapes, payloads. */

export const assetTypes: Record<string, any> = {
  building: L('بناية / مجمّع', 'Building'), apartment: L('شقة', 'Apartment'), villa: L('فيلا', 'Villa'), shop: L('محل تجاري', 'Shop'),
  office: L('مكتب', 'Office'), warehouse: L('مستودع', 'Warehouse'), land: L('أرض', 'Land'), vehicle: L('سيارة', 'Vehicle'), other: L('أخرى', 'Other'),
}
export const assetTypeIcon: Record<string, string> = {
  building: 'ti-building', apartment: 'ti-building-community', villa: 'ti-home', shop: 'ti-building-store', office: 'ti-briefcase',
  warehouse: 'ti-building-warehouse', land: 'ti-map-2', vehicle: 'ti-car', other: 'ti-box',
}
export const assetStatus: Record<string, { label: string; cls: string }> = {
  available: L('متاح', 'Available', { cls: 'ok' }), rented: L('مؤجّر', 'Rented', { cls: 'info' }),
  maintenance: L('صيانة', 'Maintenance', { cls: 'warn' }), inactive: L('موقوف', 'Inactive', { cls: 'muted' }),
}
export const frequencies: Record<string, any> = {
  daily: L('يومي', 'Daily'), weekly: L('أسبوعي', 'Weekly'), monthly: L('شهري', 'Monthly'), quarterly: L('ربع سنوي', 'Quarterly'),
  semiannual: L('نصف سنوي', 'Semi-annual'), annual: L('سنوي', 'Annual'), once: L('دفعة واحدة', 'One payment'),
}
export const contractStatus: Record<string, { label: string; cls: string }> = {
  active: L('ساري', 'Active', { cls: 'ok' }), ended: L('منتهٍ', 'Ended', { cls: 'muted' }), terminated: L('مفسوخ', 'Terminated', { cls: 'bad' }),
}
export const installmentStatus: Record<string, { label: string; cls: string }> = {
  due: L('مستحق', 'Due', { cls: 'warn' }), partial: L('مدفوع جزئياً', 'Partial', { cls: 'info' }), paid: L('مدفوع', 'Paid', { cls: 'ok' }), cancelled: L('ملغى', 'Cancelled', { cls: 'muted' }),
}
export const depositStatus: Record<string, any> = { none: L('لم يُحصّل', 'Not collected'), held: L('محتجز', 'Held'), returned: L('مُعاد', 'Returned') }
export const paymentKind: Record<string, any> = { rent: L('دفعة', 'Payment'), deposit: L('تأمين', 'Deposit'), deposit_return: L('إرجاع تأمين', 'Deposit return') }

/** Sector-aware wording: the same engine reads as properties, a fleet, or installment sales. */
export function useRentalTerms() {
  const auth = useAuth()
  const key = computed(() => auth.state.value.identity?.key || 'real_estate')
  const isVehicle = computed(() => ['car_rental', 'car_showroom'].includes(key.value))
  const isSale = computed(() => key.value === 'car_showroom')
  return reactive({
    isVehicle, isSale,
    asset: computed(() => isVehicle.value ? tt('سيارة', 'Vehicle') : tt('عقار', 'Property')),
    assets: computed(() => isVehicle.value ? tt('السيارات', 'Vehicles') : tt('العقارات والوحدات', 'Properties & Units')),
    newAsset: computed(() => isVehicle.value ? tt('سيارة جديدة', 'New vehicle') : tt('عقار / وحدة جديدة', 'New property / unit')),
    renter: computed(() => isSale.value ? tt('المشتري', 'Buyer') : tt('المستأجر', 'Tenant')),
    renters: computed(() => isSale.value ? tt('المشترون', 'Buyers') : tt('المستأجرون', 'Tenants')),
    rent: computed(() => isSale.value ? tt('القسط', 'Installment') : tt('الإيجار', 'Rent')),
    /** Types offered in the asset form for this sector. */
    types: computed(() => isVehicle.value ? ['vehicle', 'other'] : ['building', 'apartment', 'villa', 'shop', 'office', 'warehouse', 'land', 'other']),
  })
}

export const blankAsset = (isVehicle = false) => ({
  parent_id: '', owner_id: '', code: '', name: '', type: isVehicle ? 'vehicle' : 'apartment', status: 'available',
  city: '', address: '', area: '', rooms: '', bathrooms: '', floor: '', furnished: false, electricity_meter: '', water_meter: '',
  plate_no: '', make: '', model: '', year: '', color: '', vin: '', odometer: '',
  rent_amount: 0, rent_frequency: isVehicle ? 'daily' : 'monthly', deposit: 0, description: '',
})
export const assetToForm = (a: any) => { const f: any = blankAsset(); for (const k of Object.keys(f)) f[k] = a[k] ?? ''; f.furnished = !!a.furnished; f.rent_amount = +a.rent_amount || 0; f.deposit = +a.deposit || 0; return f }
export const assetPayload = (f: any) => {
  const out: any = { ...f }
  for (const k of ['parent_id', 'owner_id', 'area', 'rooms', 'bathrooms', 'year', 'odometer']) out[k] = f[k] === '' || f[k] === null ? null : f[k]
  return out
}

export const blankOwner = () => ({ name: '', type: 'owner', phone: '', email: '', national_id: '', commission_rate: 0, bank_name: '', iban: '', notes: '', is_active: true })

const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
export const blankContract = (isSale = false) => {
  const s = new Date(); const e = new Date(s.getFullYear() + 1, s.getMonth(), s.getDate() - 1)
  return {
    kind: isSale ? 'installment' : 'lease', asset_id: '', customer_id: '', new_customer: { name: '', phone: '', id_number: '' },
    start_date: iso(s), end_date: iso(e), frequency: 'monthly', rent_amount: 0, down_payment: 0, deposit: 0, terms: '', notes: '',
  }
}
export const contractToForm = (c: any) => ({
  kind: c.kind, asset_id: c.asset_id, customer_id: c.customer_id, new_customer: { name: '', phone: '', id_number: '' },
  start_date: c.start_date, end_date: c.end_date, frequency: c.frequency, rent_amount: +c.rent_amount, down_payment: +c.down_payment,
  deposit: +c.deposit, terms: c.terms || '', notes: c.notes || '',
})
export const contractPayload = (f: any) => ({ ...f, customer_id: f.customer_id || null, new_customer: f.customer_id ? null : f.new_customer })

/** Days late (positive) for an open installment, else 0. */
export const daysLate = (due: string) => { const d = Math.floor((Date.now() - new Date(due + 'T00:00:00').getTime()) / 86400000); return d > 0 ? d : 0 }
