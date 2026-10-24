/** Service engine (work orders) UI helpers. */

export const orderStatus: Record<string, { label: string; cls: string; icon: string }> = {
  received: L('مستلم', 'Received', { cls: 'info', icon: 'ti-inbox' }),
  in_progress: L('قيد العمل', 'In progress', { cls: 'warn', icon: 'ti-tool' }),
  waiting_parts: L('بانتظار قطع', 'Waiting parts', { cls: 'muted', icon: 'ti-hourglass' }),
  ready: L('جاهز للتسليم', 'Ready', { cls: 'ok', icon: 'ti-circle-check' }),
  delivered: L('تم التسليم', 'Delivered', { cls: 'ok', icon: 'ti-truck-delivery' }),
  cancelled: L('ملغى', 'Cancelled', { cls: 'bad', icon: 'ti-ban' }),
}
export const OPEN_ORDER = ['received', 'in_progress', 'waiting_parts', 'ready']

type Field = { key: string; ar: string; en: string; type?: 'text' | 'number' | 'select' | 'switch' | 'textarea'; options?: Record<string, [string, string]>; ltr?: boolean }

/** Per-sector fields stored in order.details. */
const FIELDS: Record<string, { title: [string, string]; icon: string; fields: Field[] }> = {
  auto_garage: {
    title: ['بيانات السيارة', 'Vehicle'], icon: 'ti-car',
    fields: [
      { key: 'make', ar: 'الشركة', en: 'Make' }, { key: 'model', ar: 'الطراز', en: 'Model' }, { key: 'year', ar: 'سنة الصنع', en: 'Year', type: 'number', ltr: true },
      { key: 'plate', ar: 'رقم اللوحة', en: 'Plate', ltr: true }, { key: 'color', ar: 'اللون', en: 'Color' }, { key: 'odometer', ar: 'العدّاد (كم)', en: 'Odometer (km)', type: 'number', ltr: true },
      { key: 'vin', ar: 'رقم الهيكل', en: 'VIN', ltr: true },
    ],
  },
  laundry: {
    title: ['تفاصيل الطلب', 'Order details'], icon: 'ti-wash-machine',
    fields: [
      { key: 'pieces', ar: 'عدد القطع', en: 'Pieces', type: 'number', ltr: true },
      { key: 'service', ar: 'نوع الخدمة', en: 'Service', type: 'select', options: { wash_iron: ['غسيل وكوي', 'Wash & iron'], iron: ['كوي فقط', 'Iron only'], dry_clean: ['تنظيف جاف', 'Dry clean'], wash: ['غسيل فقط', 'Wash only'] } },
      { key: 'bag', ar: 'رقم الكيس / التذكرة', en: 'Bag / tag no.', ltr: true }, { key: 'express', ar: 'مستعجل', en: 'Express', type: 'switch' },
    ],
  },
  maintenance: {
    title: ['موقع البلاغ', 'Site'], icon: 'ti-map-pin',
    fields: [
      { key: 'issue_type', ar: 'نوع العطل', en: 'Issue type', type: 'select', options: { ac: ['تكييف', 'A/C'], electric: ['كهرباء', 'Electrical'], plumbing: ['سباكة', 'Plumbing'], carpentry: ['نجارة', 'Carpentry'], painting: ['دهانات', 'Painting'], other: ['أخرى', 'Other'] } },
      { key: 'address', ar: 'العنوان', en: 'Address' }, { key: 'building', ar: 'المبنى / الشقة', en: 'Building / unit' }, { key: 'contact', ar: 'جهة التواصل بالموقع', en: 'On-site contact' },
    ],
  },
  tailoring: {
    title: ['القطعة والمقاسات', 'Garment & measurements'], icon: 'ti-needle-thread',
    fields: [
      { key: 'garment', ar: 'القطعة', en: 'Garment', type: 'select', options: { thobe: ['ثوب', 'Thobe'], abaya: ['عباية', 'Abaya'], dress: ['فستان', 'Dress'], suit: ['بدلة', 'Suit'], other: ['أخرى', 'Other'] } },
      { key: 'pieces', ar: 'عدد القطع', en: 'Pieces', type: 'number', ltr: true }, { key: 'fabric', ar: 'القماش', en: 'Fabric' },
      { key: 'length', ar: 'الطول', en: 'Length', type: 'number', ltr: true }, { key: 'chest', ar: 'الصدر', en: 'Chest', type: 'number', ltr: true },
      { key: 'shoulder', ar: 'الكتف', en: 'Shoulder', type: 'number', ltr: true }, { key: 'sleeve', ar: 'الكم', en: 'Sleeve', type: 'number', ltr: true },
      { key: 'neck', ar: 'الرقبة', en: 'Neck', type: 'number', ltr: true }, { key: 'waist', ar: 'الخصر', en: 'Waist', type: 'number', ltr: true },
      { key: 'style', ar: 'التفصيلة / ملاحظات القصّة', en: 'Style notes', type: 'textarea' },
    ],
  },
  default: {
    title: ['بيانات الجهاز / العمل', 'Item details'], icon: 'ti-device-desktop',
    fields: [{ key: 'device', ar: 'الجهاز / القطعة', en: 'Item' }, { key: 'brand', ar: 'الماركة', en: 'Brand' }, { key: 'serial', ar: 'الرقم التسلسلي', en: 'Serial', ltr: true }],
  },
}

export function useServiceSector() {
  const auth = useAuth()
  const key = computed(() => auth.state.value.identity?.key || 'default')
  return reactive({
    key,
    spec: computed(() => FIELDS[key.value] || FIELDS.default),
    order: computed(() => ({ auto_garage: tt('أمر إصلاح', 'Repair order'), laundry: tt('طلب غسيل', 'Laundry order'), maintenance: tt('بلاغ صيانة', 'Maintenance ticket'), tailoring: tt('طلب تفصيل', 'Tailoring order') } as any)[key.value] || tt('أمر عمل', 'Work order')),
    orders: computed(() => ({ auto_garage: tt('أوامر الإصلاح', 'Repair orders'), laundry: tt('طلبات الغسيل', 'Laundry orders'), maintenance: tt('بلاغات الصيانة', 'Tickets'), tailoring: tt('طلبات التفصيل', 'Tailoring orders') } as any)[key.value] || tt('أوامر العمل', 'Work orders')),
    titleLabel: computed(() => ({ auto_garage: tt('الشكوى / المطلوب', 'Complaint / request'), laundry: tt('وصف الطلب', 'Request'), maintenance: tt('وصف العطل', 'Issue'), tailoring: tt('المطلوب', 'Request') } as any)[key.value] || tt('المطلوب', 'Request')),
    tech: computed(() => ({ laundry: tt('العامل', 'Staff'), tailoring: tt('الخيّاط', 'Tailor') } as any)[key.value] || tt('الفني', 'Technician')),
    techs: computed(() => ({ laundry: tt('العمّال', 'Staff'), tailoring: tt('الخيّاطون', 'Tailors') } as any)[key.value] || tt('الفنيون', 'Technicians')),
    usesStock: computed(() => !!auth.state.value.sidebar?.some((i: any) => i.key === 'inventory')),
  })
}
/** Short summary of details for lists ("تويوتا كامري · أ ب ج 1234"). */
export function detailsSummary(key: string, d: any): string {
  if (!d) return ''
  if (key === 'auto_garage') return [d.make, d.model, d.year, d.plate].filter(Boolean).join(' · ')
  if (key === 'laundry') return [d.pieces ? `${d.pieces} ${tt('قطعة', 'pcs')}` : '', d.bag ? `#${d.bag}` : '', d.express ? tt('مستعجل', 'express') : ''].filter(Boolean).join(' · ')
  if (key === 'maintenance') return [d.address, d.building].filter(Boolean).join(' · ')
  if (key === 'tailoring') return [d.pieces ? `${d.pieces} ${tt('قطعة', 'pcs')}` : '', d.fabric].filter(Boolean).join(' · ')
  return [d.device, d.brand, d.serial].filter(Boolean).join(' · ')
}
export const fieldLabel = (f: Field) => tt(f.ar, f.en)
export const optionLabel = (f: Field, v: string) => (f.options?.[v] ? tt(f.options[v][0], f.options[v][1]) : v)

const pad = (n: number) => String(n).padStart(2, '0')
const dtLocal = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
export const blankOrder = () => ({
  customer_id: '', customer_name: '', customer_phone: '', title: '', details: {} as Record<string, any>, priority: 'normal', technician_id: '',
  received_at: dtLocal(new Date()), promised_at: dtLocal(new Date(Date.now() + 86400000)), estimate: 0, discount: 0, warehouse_id: '',
  diagnosis: '', notes: '', internal_notes: '', lines: [] as any[],
})
export const orderToForm = (o: any) => ({
  ...blankOrder(), customer_id: o.customer_id || '', customer_name: o.customer_name || '', customer_phone: o.customer_phone || '', title: o.title,
  details: { ...(o.details || {}) }, priority: o.priority, technician_id: o.technician_id || '', received_at: (o.received_at || '').replace(' ', 'T'),
  promised_at: (o.promised_at || '').replace(' ', 'T'), estimate: +o.estimate, discount: +o.discount, warehouse_id: o.warehouse_id || '',
  diagnosis: o.diagnosis || '', notes: o.notes || '', internal_notes: o.internal_notes || '',
  lines: (o.lines || []).map((l: any) => ({ kind: l.kind, item_id: l.item_id, catalog_id: l.catalog_id, description: l.description, qty: +l.qty, unit_price: +l.unit_price, unit_cost: +l.unit_cost, stock: l.item?.track_stock })),
})
export const orderPayload = (f: any) => ({
  ...f, customer_id: f.customer_id || null, technician_id: f.technician_id || null, warehouse_id: f.warehouse_id || null,
  received_at: f.received_at ? f.received_at.replace('T', ' ') : null, promised_at: f.promised_at ? f.promised_at.replace('T', ' ') : null,
})
export const orderTotal = (f: any) => Math.max(0, Math.round(((f.lines || []).reduce((n: number, l: any) => n + (+l.qty || 0) * (+l.unit_price || 0), 0) - (+f.discount || 0)) * 1000) / 1000)
