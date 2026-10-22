/** HR UI helpers: labels, form shapes, payloads. */
export const employmentTypes: Record<string, string> = { full_time: 'دوام كامل', part_time: 'دوام جزئي', contract: 'عقد', daily: 'يومي' }
export const employeeStatus: Record<string, { label: string; cls: string }> = { active: { label: 'على رأس العمل', cls: 'ok' }, on_leave: { label: 'في إجازة', cls: 'warn' }, terminated: { label: 'منتهي الخدمة', cls: 'muted' } }
export const attendanceStatus: Record<string, { label: string; cls: string }> = {
  present: { label: 'حاضر', cls: 'ok' }, late: { label: 'متأخر', cls: 'warn' }, half_day: { label: 'نصف يوم', cls: 'info' }, absent: { label: 'غائب', cls: 'bad' },
  leave: { label: 'إجازة', cls: 'muted' }, holiday: { label: 'عطلة رسمية', cls: 'muted' }, weekend: { label: 'نهاية أسبوع', cls: 'muted' },
}
export const leaveTypes: Record<string, string> = { annual: 'سنوية', sick: 'مرضية', unpaid: 'بدون راتب', emergency: 'طارئة', maternity: 'أمومة', other: 'أخرى' }
export const leaveStatus: Record<string, { label: string; cls: string }> = { pending: { label: 'بانتظار الموافقة', cls: 'warn' }, approved: { label: 'موافَق عليها', cls: 'ok' }, rejected: { label: 'مرفوضة', cls: 'bad' } }
export const payrollStatus: Record<string, { label: string; cls: string }> = { draft: { label: 'مسودّة', cls: 'warn' }, approved: { label: 'معتمد', cls: 'info' }, paid: { label: 'مدفوع', cls: 'ok' } }
export const maritalStatus: Record<string, string> = { single: 'أعزب', married: 'متزوج', divorced: 'مطلّق', widowed: 'أرمل' }

export const blankEmployee = () => ({
  code: '', name: '', name_en: '', job_title: '', department: '', employment_type: 'full_time', hire_date: new Date().toISOString().slice(0, 10), end_date: '', status: 'active',
  phone: '', email: '', national_id: '', nationality: '', birth_date: '', gender: '', marital_status: '', address: '', bank_name: '', iban: '',
  basic_salary: 0, housing_allowance: 0, transport_allowance: 0, other_allowance: 0, fixed_deductions: 0, annual_leave_days: 30,
  user_id: '', emergency_name: '', emergency_phone: '', notes: '',
})
export const employeeToForm = (e: any) => {
  const f: any = blankEmployee()
  for (const k of Object.keys(f)) f[k] = e[k] ?? ''
  for (const k of ['basic_salary', 'housing_allowance', 'transport_allowance', 'other_allowance', 'fixed_deductions']) f[k] = +e[k] || 0
  f.annual_leave_days = e.annual_leave_days ?? 30; f.employment_type = e.employment_type || 'full_time'; f.status = e.status || 'active'; f.user_id = e.user_id || ''
  return f
}
export const employeePayload = (f: any) => ({ ...f, user_id: f.user_id || null, end_date: f.end_date || null, hire_date: f.hire_date || null, birth_date: f.birth_date || null, gender: f.gender || null, marital_status: f.marital_status || null, employment_type: f.employment_type || null })
export const grossOf = (f: any) => Math.round(((+f.basic_salary || 0) + (+f.housing_allowance || 0) + (+f.transport_allowance || 0) + (+f.other_allowance || 0)) * 1000) / 1000

export const blankLeave = (employeeId: any = '') => ({ employee_id: employeeId, type: 'annual', from: new Date().toISOString().slice(0, 10), to: new Date().toISOString().slice(0, 10), reason: '', note: '' })
export const leaveDays = (from: string, to: string) => { if (!from || !to) return 0; const d = Math.round((new Date(to + 'T00:00:00').getTime() - new Date(from + 'T00:00:00').getTime()) / 86400000) + 1; return d > 0 ? d : 0 }
export const monthLabel = (ym: string) => { const [y, m] = ym.split('-'); return `${['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'][+m - 1]} ${y}` }
export const thisMonth = () => new Date().toISOString().slice(0, 7)
