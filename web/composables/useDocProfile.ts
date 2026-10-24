/**
 * How invoices / quotes look and behave in each sector: naming, the record an
 * invoice is issued against (case, student, contract, work order, booking),
 * which line columns make sense, and quick line presets.
 *
 * lineMode:
 *   items    — products: qty · unit · unit price · line discount (+ stock picker)
 *   services — services: qty · unit price
 *   amount   — fees: description · amount only
 */
export type LineMode = 'items' | 'services' | 'amount'
export interface DocLink { type: 'project' | 'student' | 'contract' | 'service_order' | 'booking'; label: [string, string]; module: string }
export interface DocProfile {
  invoice: [string, string]; quote: [string, string]; party: [string, string]
  cash: boolean; link: DocLink | null; ref: [string, string]; subject: [string, string]
  lineMode: LineMode; desc: [string, string]; unit?: string; presets: [string, string][]
}

const base: DocProfile = {
  invoice: ['فاتورة', 'Invoice'], quote: ['عرض سعر', 'Quote'], party: ['العميل', 'Customer'], cash: true, link: null,
  ref: ['المرجع', 'Reference'], subject: ['مثال: توريد مواد', 'e.g. supply of goods'], lineMode: 'items', desc: ['وصف البند', 'Item description'], presets: [],
}
const P = (p: Partial<DocProfile>): DocProfile => ({ ...base, ...p })

const PROFILES: Record<string, DocProfile> = {
  // ---- trade: products from stock
  retail: P({ invoice: ['فاتورة مبيعات', 'Sales invoice'], subject: ['مثال: طلبية بالجملة', 'e.g. bulk order'] }),
  sweets_shop: P({ invoice: ['فاتورة مبيعات', 'Sales invoice'], subject: ['مثال: طلبية مناسبة', 'e.g. event order'], unit: 'كغ', presets: [['توصيل', 'Delivery'], ['تغليف هدايا', 'Gift wrapping']] }),
  wholesale: P({ invoice: ['فاتورة مبيعات جملة', 'Wholesale invoice'], quote: ['عرض أسعار جملة', 'Wholesale quote'], party: ['عميل الجملة', 'Wholesale client'], cash: false, ref: ['رقم أمر الشراء', 'PO number'], subject: ['مثال: توريد شهر أكتوبر', 'e.g. October supply'], presets: [['أجور نقل', 'Freight']] }),
  farm: P({ invoice: ['فاتورة بيع محصول', 'Produce invoice'], party: ['التاجر', 'Trader'], ref: ['رقم الإرسالية', 'Shipment no.'], subject: ['مثال: إرسالية طماطم', 'e.g. tomato shipment'], unit: 'كغ', presets: [['أجور نقل', 'Freight'], ['صناديق / عبوات', 'Crates']] }),
  sweets_factory: P({ invoice: ['فاتورة مبيعات', 'Sales invoice'], party: ['الموزّع', 'Distributor'], cash: false, ref: ['رقم أمر الشراء', 'PO number'], subject: ['مثال: توريد أسبوعي', 'e.g. weekly supply'] }),
  manufacturing: P({ invoice: ['فاتورة مبيعات', 'Sales invoice'], cash: false, ref: ['رقم أمر الشراء', 'PO number'], subject: ['مثال: توريد دفعة إنتاج', 'e.g. batch delivery'] }),
  car_showroom: P({ invoice: ['فاتورة بيع', 'Sales invoice'], party: ['المشتري', 'Buyer'], cash: false, ref: ['رقم الهيكل / العقد', 'VIN / contract'], subject: ['مثال: بيع سيارة كامري 2024', 'e.g. sale of a 2024 Camry'], link: { type: 'contract', label: ['عقد التقسيط', 'Installment contract'], module: 'rental' }, presets: [['رسوم تسجيل ونقل ملكية', 'Registration & transfer'], ['تأمين', 'Insurance']] }),

  // ---- services / work orders
  auto_garage: P({ invoice: ['فاتورة إصلاح', 'Repair invoice'], quote: ['تقدير إصلاح', 'Repair estimate'], ref: ['رقم أمر الإصلاح', 'Repair order no.'], subject: ['مثال: صيانة دورية — كامري', 'e.g. service — Camry'], link: { type: 'service_order', label: ['أمر الإصلاح', 'Repair order'], module: 'service' }, presets: [['أجرة يد', 'Labor'], ['فحص كمبيوتر', 'Diagnostics']] }),
  maintenance: P({ invoice: ['فاتورة صيانة', 'Maintenance invoice'], quote: ['عرض صيانة', 'Maintenance quote'], cash: false, ref: ['رقم البلاغ', 'Ticket no.'], subject: ['مثال: صيانة تكييف — فيلا النخيل', 'e.g. A/C maintenance'], lineMode: 'services', link: { type: 'service_order', label: ['بلاغ الصيانة', 'Ticket'], module: 'service' }, presets: [['زيارة فني', 'Technician visit'], ['أجرة يد', 'Labor'], ['مواد', 'Materials']] }),
  laundry: P({ invoice: ['فاتورة غسيل', 'Laundry invoice'], ref: ['رقم الطلب', 'Order no.'], subject: ['مثال: غسيل وكوي', 'e.g. wash & iron'], lineMode: 'services', unit: 'قطعة', link: { type: 'service_order', label: ['طلب الغسيل', 'Laundry order'], module: 'service' }, presets: [['غسيل وكوي ثوب', 'Thobe wash & iron'], ['تنظيف جاف', 'Dry clean'], ['توصيل', 'Delivery']] }),
  tailoring: P({ invoice: ['فاتورة تفصيل', 'Tailoring invoice'], ref: ['رقم الطلب', 'Order no.'], subject: ['مثال: تفصيل 3 ثياب', 'e.g. 3 thobes'], lineMode: 'services', unit: 'قطعة', link: { type: 'service_order', label: ['طلب التفصيل', 'Tailoring order'], module: 'service' }, presets: [['تفصيل ثوب', 'Thobe tailoring'], ['قماش', 'Fabric'], ['تعديل', 'Alteration']] }),

  // ---- professional fees
  law_firm: P({ invoice: ['فاتورة أتعاب', 'Fee invoice'], quote: ['عرض أتعاب', 'Fee proposal'], party: ['الموكّل', 'Client'], cash: false, ref: ['رقم القضية', 'Case no.'], subject: ['مثال: أتعاب القضية التجارية — المرحلة الابتدائية', 'e.g. commercial case fees — first instance'], lineMode: 'amount', desc: ['بيان الأتعاب', 'Fee description'], link: { type: 'project', label: ['القضية', 'Case'], module: 'projects' }, presets: [['أتعاب ترافع', 'Pleading fees'], ['استشارة قانونية', 'Legal consultation'], ['صياغة / مراجعة عقد', 'Contract drafting'], ['رسوم ومصاريف قضائية', 'Court fees & expenses'], ['دفعة من الأتعاب المتفق عليها', 'Installment of agreed fee']] }),
  engineering: P({ invoice: ['فاتورة أتعاب هندسية', 'Engineering invoice'], quote: ['عرض فني ومالي', 'Proposal'], cash: false, ref: ['رقم المشروع', 'Project no.'], subject: ['مثال: مستخلص رقم 2 — تصميم فيلا', 'e.g. progress claim 2'], lineMode: 'services', link: { type: 'project', label: ['المشروع', 'Project'], module: 'projects' }, presets: [['دفعة مقدمة من قيمة العقد', 'Advance payment'], ['أتعاب التصميم', 'Design fees'], ['إشراف شهري', 'Monthly supervision'], ['رسوم رخصة بناء', 'Permit fees']] }),
  accounting_firm: P({ invoice: ['فاتورة خدمات', 'Service invoice'], quote: ['عرض خدمات', 'Service proposal'], party: ['الشركة العميلة', 'Client company'], cash: false, ref: ['رقم العقد', 'Contract no.'], subject: ['مثال: خدمات محاسبية — أكتوبر', 'e.g. bookkeeping — October'], lineMode: 'amount', desc: ['بيان الخدمة', 'Service'], presets: [['مسك دفاتر شهري', 'Monthly bookkeeping'], ['إعداد إقرار ضريبي', 'Tax return'], ['مراجعة القوائم المالية', 'Financial statements review'], ['استشارة مالية', 'Financial consultation']] }),
  school: P({ invoice: ['فاتورة رسوم', 'Fee invoice'], party: ['ولي الأمر', 'Guardian'], cash: false, ref: ['رقم الطالب', 'Student no.'], subject: ['مثال: رسوم الفصل الأول', 'e.g. term 1 fees'], lineMode: 'amount', desc: ['بيان الرسوم', 'Fee'], link: { type: 'student', label: ['الطالب', 'Student'], module: 'education' }, presets: [['رسوم دراسية', 'Tuition'], ['رسوم نقل', 'Transport'], ['زي مدرسي', 'Uniform'], ['كتب وقرطاسية', 'Books'], ['رسوم نشاط / رحلة', 'Activity / trip']] }),

  // ---- rentals
  real_estate: P({ invoice: ['فاتورة إيجار', 'Rent invoice'], party: ['المستأجر', 'Tenant'], cash: false, ref: ['رقم العقد', 'Contract no.'], subject: ['مثال: إيجار شهر نوفمبر — شقة 101', 'e.g. November rent'], lineMode: 'amount', desc: ['البيان', 'Description'], link: { type: 'contract', label: ['عقد الإيجار', 'Lease'], module: 'rental' }, presets: [['إيجار شهري', 'Monthly rent'], ['رسوم خدمات وصيانة', 'Service charges'], ['كهرباء', 'Electricity'], ['مياه', 'Water'], ['غرامة تأخير', 'Late fee']] }),
  car_rental: P({ invoice: ['فاتورة تأجير', 'Rental invoice'], party: ['المستأجر', 'Renter'], ref: ['رقم العقد', 'Contract no.'], subject: ['مثال: تأجير كامري — أسبوع', 'e.g. Camry — 1 week'], lineMode: 'services', unit: 'يوم', link: { type: 'contract', label: ['عقد التأجير', 'Rental contract'], module: 'rental' }, presets: [['إيجار يومي', 'Daily rent'], ['كيلومترات إضافية', 'Extra km'], ['تأمين شامل', 'Full insurance'], ['غرامة / مخالفة', 'Fine']] }),

  // ---- hospitality
  hotel: P({ invoice: ['فاتورة إقامة', 'Stay invoice'], party: ['النزيل', 'Guest'], ref: ['رقم الحجز', 'Booking no.'], subject: ['مثال: إقامة 3 ليالٍ', 'e.g. 3-night stay'], lineMode: 'services', unit: 'ليلة', link: { type: 'booking', label: ['الحجز', 'Booking'], module: 'bookings' }, presets: [['إقامة', 'Accommodation'], ['إفطار', 'Breakfast'], ['خدمة غرف', 'Room service'], ['مغسلة', 'Laundry']] }),
  chalets: P({ invoice: ['فاتورة حجز', 'Booking invoice'], ref: ['رقم الحجز', 'Booking no.'], subject: ['مثال: حجز شاليه — عطلة نهاية الأسبوع', 'e.g. weekend chalet'], lineMode: 'services', link: { type: 'booking', label: ['الحجز', 'Booking'], module: 'bookings' }, presets: [['إيجار الشاليه', 'Chalet rent'], ['خدمات إضافية', 'Extras']] }),
  halls: P({ invoice: ['فاتورة مناسبة', 'Event invoice'], quote: ['عرض مناسبة', 'Event quote'], ref: ['رقم الحجز', 'Booking no.'], subject: ['مثال: حفل زفاف — قاعة النخيل', 'e.g. wedding — Palm Hall'], lineMode: 'services', link: { type: 'booking', label: ['الحجز', 'Booking'], module: 'bookings' }, presets: [['إيجار القاعة', 'Hall rent'], ['بوفيه / ضيافة', 'Catering'], ['كوشة وتنسيق', 'Stage & decor'], ['تصوير', 'Photography'], ['صوتيات', 'Sound']] }),
  travel: P({ invoice: ['فاتورة رحلة', 'Trip invoice'], party: ['العميل', 'Customer'], ref: ['رقم الرحلة', 'Trip no.'], subject: ['مثال: باقة عمرة رمضان', 'e.g. Ramadan Umrah package'], lineMode: 'services', unit: 'شخص', presets: [['باقة الرحلة', 'Trip package'], ['تأشيرة', 'Visa'], ['تذكرة طيران', 'Flight'], ['فرق غرفة', 'Room upgrade']] }),
}

export function useDocProfile() {
  const auth = useAuth()
  const key = computed(() => auth.state.value.identity?.key || 'default')
  const profile = computed<DocProfile>(() => PROFILES[key.value] || base)
  const enabled = (module: string) => !!auth.state.value.sidebar?.some((i: any) => i.key === module)
  /** The record link is offered only when its module is enabled for the tenant. */
  const link = computed(() => (profile.value.link && enabled(profile.value.link.module) ? profile.value.link : null))
  return { key, profile, link }
}

/** Options for the "issued against" selector: {id, label, customer_id, reference, subject, student_id?}. */
export async function loadDocLinks(type: DocLink['type']): Promise<any[]> {
  const api = useApi()
  try {
    if (type === 'project') return (await api.get('/app/projects/items', { status: 'all' })).page.data.map((p: any) => ({ id: `p${p.id}`, label: `${p.number} — ${p.title}`, customer_id: p.customer_id, reference: p.court_case_no || p.number, subject: p.title }))
    if (type === 'student') return (await api.get('/app/education/students')).page.data.map((s: any) => ({ id: `s${s.id}`, label: `${s.name}${s.edu_class ? ' — ' + s.edu_class.name : ''}`, customer_id: s.guardian_id, reference: s.student_no, subject: s.name, student_id: s.id }))
    if (type === 'contract') return (await api.get('/app/rental/contracts', { status: '' })).page.data.map((c: any) => ({ id: `c${c.id}`, label: `${c.number} — ${c.customer?.name || ''} · ${c.asset?.name || ''}`, customer_id: c.customer_id, reference: c.number, subject: c.asset?.name }))
    if (type === 'service_order') return (await api.get('/app/service/orders', { status: 'all' })).page.data.map((o: any) => ({ id: `o${o.id}`, label: `${o.number} — ${o.customer?.name || o.customer_name || ''} · ${o.title}`, customer_id: o.customer_id, reference: o.number, subject: o.title }))
    if (type === 'booking') { const r: any = await api.get('/app/bookings/list'); return (r.data || r.page?.data || []).map((b: any) => ({ id: `b${b.id}`, label: `${b.number} — ${b.customer?.name || b.customer_name || ''} · ${b.unit?.name || ''}`, customer_id: b.customer_id, reference: b.number, subject: b.unit?.name })) }
  } catch { /* module not reachable for this user */ }
  return []
}
