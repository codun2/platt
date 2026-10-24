/** Projects engine UI helpers (legal cases + engineering projects). */

export const projectStatus: Record<string, { label: string; cls: string }> = {
  open: L('مفتوح', 'Open', { cls: 'info' }), in_progress: L('قيد العمل', 'In progress', { cls: 'warn' }), on_hold: L('معلّق', 'On hold', { cls: 'muted' }),
  closed: L('مغلق', 'Closed', { cls: 'muted' }), won: L('كسب', 'Won', { cls: 'ok' }), lost: L('خسارة', 'Lost', { cls: 'bad' }), settled: L('صلح / تسوية', 'Settled', { cls: 'ok' }),
}
export const caseTypes: Record<string, any> = {
  civil: L('مدنية', 'Civil'), commercial: L('تجارية', 'Commercial'), criminal: L('جزائية', 'Criminal'), labor: L('عمالية', 'Labor'),
  family: L('أحوال شخصية', 'Family'), administrative: L('إدارية', 'Administrative'), real_estate: L('عقارية', 'Real estate'), other: L('أخرى', 'Other'),
}
export const caseStages: Record<string, any> = {
  consultation: L('استشارة', 'Consultation'), first_instance: L('ابتدائي', 'First instance'), appeal: L('استئناف', 'Appeal'),
  cassation: L('تمييز / نقض', 'Cassation'), execution: L('تنفيذ', 'Execution'),
}
export const clientRoles: Record<string, any> = { plaintiff: L('مدّعٍ', 'Plaintiff'), defendant: L('مدّعى عليه', 'Defendant'), other: L('أخرى', 'Other') }
export const feeTypes: Record<string, any> = { hourly: L('بالساعة', 'Hourly'), fixed: L('مبلغ مقطوع', 'Fixed fee'), retainer: L('اشتراك دوري', 'Retainer') }
export const priorities: Record<string, { label: string; cls: string }> = {
  low: L('منخفضة', 'Low', { cls: 'muted' }), normal: L('عادية', 'Normal', { cls: 'info' }), high: L('مرتفعة', 'High', { cls: 'warn' }), urgent: L('عاجلة', 'Urgent', { cls: 'bad' }),
}
export const taskStatus: Record<string, { label: string; cls: string }> = {
  todo: L('قادمة', 'To do', { cls: 'info' }), doing: L('قيد التنفيذ', 'In progress', { cls: 'warn' }), done: L('منجزة', 'Done', { cls: 'ok' }),
  postponed: L('مؤجّلة', 'Postponed', { cls: 'muted' }), cancelled: L('ملغاة', 'Cancelled', { cls: 'muted' }),
}
export const CLOSED_STATUSES = ['closed', 'won', 'lost', 'settled']

/** Wording for a kind: case (law firm) or project (engineering). */
export function projectTerms(kind: 'case' | 'project') {
  const c = kind === 'case'
  return {
    kind, isCase: c,
    one: c ? tt('قضية', 'Case') : tt('مشروع', 'Project'),
    many: c ? tt('القضايا', 'Cases') : tt('المشاريع', 'Projects'),
    newOne: c ? tt('قضية جديدة', 'New case') : tt('مشروع جديد', 'New project'),
    client: c ? tt('الموكّل', 'Client') : tt('العميل', 'Client'),
    lead: c ? tt('المحامي المسؤول', 'Lead lawyer') : tt('مدير المشروع', 'Project manager'),
    base: c ? '/projects/cases' : '/projects/list',
    icon: c ? 'ti-gavel' : 'ti-ruler-2',
  }
}
/** Which kind this account works with (from its sector). */
export function useProjectKind() {
  const auth = useAuth()
  return computed<'case' | 'project'>(() => auth.state.value.identity?.key === 'law_firm' ? 'case' : 'project')
}

export const blankProject = (kind: 'case' | 'project') => ({
  kind, title: '', customer_id: '', status: 'open', priority: 'normal', lead_id: '', start_date: new Date().toISOString().slice(0, 10), due_date: '',
  case_type: kind === 'case' ? 'commercial' : '', court: '', court_case_no: '', client_role: kind === 'case' ? 'plaintiff' : '', opponent: '', opponent_lawyer: '',
  stage: kind === 'case' ? 'first_instance' : '', location: '', contract_value: 0, progress: 0,
  fee_type: kind === 'case' ? 'hourly' : 'fixed', fee_amount: 0, hourly_rate: 0, description: '', notes: '',
})
export const projectToForm = (p: any) => { const f: any = blankProject(p.kind); for (const k of Object.keys(f)) f[k] = p[k] ?? ''; for (const k of ['contract_value', 'fee_amount', 'hourly_rate', 'progress']) f[k] = +p[k] || 0; return f }
export const projectPayload = (f: any) => ({ ...f, customer_id: f.customer_id || null, lead_id: f.lead_id || null, start_date: f.start_date || null, due_date: f.due_date || null })

const pad = (n: number) => String(n).padStart(2, '0')
export const localDT = (d = new Date()) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
/** "2026-11-03 09:30" → "الثلاثاء 3 نوفمبر · 09:30" */
export const fmtWhen = (v: string | null) => {
  if (!v) return '—'
  const d = new Date(v.replace(' ', 'T'))
  if (isNaN(+d)) return v
  return `${d.toLocaleDateString(currentLocale() === 'en' ? 'en-GB' : 'ar', { weekday: 'long', day: 'numeric', month: 'long' })} · ${pad(d.getHours())}:${pad(d.getMinutes())}`
}
export const isOverdue = (t: any) => ['todo', 'doing'].includes(t.status) && t.due_at && new Date(t.due_at.replace(' ', 'T')) < new Date()
