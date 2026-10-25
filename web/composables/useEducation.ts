/** Education engine UI helpers. */
export const eduStages: Record<string, any> = { kindergarten: L('رياض أطفال', 'Kindergarten'), primary: L('ابتدائي', 'Primary'), middle: L('متوسط', 'Middle'), secondary: L('ثانوي', 'Secondary') }
export const studentStatus: Record<string, { label: string; cls: string }> = { active: L('منتظم', 'Active', { cls: 'ok' }), withdrawn: L('منسحب', 'Withdrawn', { cls: 'muted' }), graduated: L('متخرّج', 'Graduated', { cls: 'info' }) }
export const attendStatus: Record<string, { label: string; cls: string; icon: string }> = {
  present: L('حاضر', 'Present', { cls: 'ok', icon: 'ti-check' }), absent: L('غائب', 'Absent', { cls: 'bad', icon: 'ti-x' }),
  late: L('متأخر', 'Late', { cls: 'warn', icon: 'ti-clock' }), excused: L('بعذر', 'Excused', { cls: 'info', icon: 'ti-file-certificate' }),
}
export const guardianRelations: Record<string, any> = { father: L('الأب', 'Father'), mother: L('الأم', 'Mother'), other: L('أخرى', 'Other') }
export const currentAcademicYear = () => { const d = new Date(); const y = d.getMonth() >= 7 ? d.getFullYear() : d.getFullYear() - 1; return `${y}/${y + 1}` }
export const blankStudent = () => ({
  student_no: '', name: '', name_en: '', gender: '', birth_date: '', national_id: '', nationality: '', class_id: '', guardian_id: '', guardian_relation: 'father',
  new_guardian: { name: '', phone: '', id_number: '' }, emergency_phone: '', enrolled_at: ymd(), status: 'active',
  discount_pct: 0, transport: '', medical_notes: '', notes: '',
})
export const studentToForm = (s: any) => { const f: any = blankStudent(); for (const k of Object.keys(f)) if (k !== 'new_guardian') f[k] = s[k] ?? ''; f.discount_pct = +s.discount_pct || 0; return f }
export const studentPayload = (f: any) => ({ ...f, class_id: f.class_id || null, guardian_id: f.guardian_id || null, gender: f.gender || null, birth_date: f.birth_date || null, enrolled_at: f.enrolled_at || null, guardian_relation: f.guardian_relation || null, new_guardian: f.guardian_id ? null : f.new_guardian })
