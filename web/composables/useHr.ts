/** HR UI helpers: labels, form shapes, payloads. */
export const employmentTypes: Record<string, any> = { full_time: L('دوام كامل', 'Full-time'), part_time: L('دوام جزئي', 'Part-time'), contract: L('عقد', 'Contract'), daily: L('يومي', 'Daily') }
export const employeeStatus: Record<string, { label: string; cls: string }> = { active: L('على رأس العمل', 'Active', { cls: 'ok' }), on_leave: L('في إجازة', 'On leave', { cls: 'warn' }), terminated: L('منتهي الخدمة', 'Terminated', { cls: 'muted' }) }
export const attendanceStatus: Record<string, { label: string; cls: string }> = {
  present: L('حاضر', 'Present', { cls: 'ok' }), late: L('متأخر', 'Late', { cls: 'warn' }), half_day: L('نصف يوم', 'Half day', { cls: 'info' }), absent: L('غائب', 'Absent', { cls: 'bad' }),
  leave: L('إجازة', 'Leave', { cls: 'muted' }), holiday: L('عطلة رسمية', 'Holiday', { cls: 'muted' }), weekend: L('نهاية أسبوع', 'Weekend', { cls: 'muted' }),
}
export const leaveTypes: Record<string, any> = { annual: L('سنوية', 'Annual'), sick: L('مرضية', 'Sick'), unpaid: L('بدون راتب', 'Unpaid'), emergency: L('طارئة', 'Emergency'), maternity: L('أمومة', 'Maternity'), other: L('أخرى', 'Other') }
export const leaveStatus: Record<string, { label: string; cls: string }> = { pending: L('بانتظار الموافقة', 'Pending', { cls: 'warn' }), approved: L('موافَق عليها', 'Approved', { cls: 'ok' }), rejected: L('مرفوضة', 'Rejected', { cls: 'bad' }) }
export const payrollStatus: Record<string, { label: string; cls: string }> = { draft: L('مسودّة', 'Draft', { cls: 'warn' }), approved: L('معتمد', 'Approved', { cls: 'info' }), paid: L('مدفوع', 'Paid', { cls: 'ok' }) }
export const maritalStatus: Record<string, any> = { single: L('أعزب', 'Single'), married: L('متزوج', 'Married'), divorced: L('مطلّق', 'Divorced'), widowed: L('أرمل', 'Widowed') }

export const blankEmployee = () => ({
  code: '', name: '', name_en: '', job_title: '', department: '', employment_type: 'full_time', hire_date: ymd(), end_date: '', status: 'active',
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

export const blankLeave = (employeeId: any = '') => ({ employee_id: employeeId, type: 'annual', from: ymd(), to: ymd(), reason: '', note: '' })
export const leaveDays = (from: string, to: string) => { if (!from || !to) return 0; const d = Math.round((new Date(to + 'T00:00:00').getTime() - new Date(from + 'T00:00:00').getTime()) / 86400000) + 1; return d > 0 ? d : 0 }
export const monthLabel = (ym: string) => { const [y, m] = ym.split('-'); const ar = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر']; const en = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']; return `${tt(ar[+m - 1], en[+m - 1])} ${y}` }
export const thisMonth = () => ymd().slice(0, 7)
