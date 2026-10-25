/**
 * Number fields everywhere: letters are blocked as they are typed (Safari lets
 * them into <input type="number">, which then submits an empty value), with a
 * short message under the field; Arabic-Indic digits are converted to 0-9 in
 * every field (number and text).
 */
export default defineNuxtPlugin(() => {
  const AR = '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹'
  const toLatin = (s: string) => s.replace(/[٠-٩۰-۹]/g, d => String(AR.indexOf(d) % 10)).replace('٫', '.')
  const allowed = /^[0-9.\-]*$/

  function warn(input: HTMLInputElement) {
    input.setCustomValidity(tt('يجب إدخال أرقام فقط', 'Numbers only'))
    input.reportValidity()
    setTimeout(() => input.setCustomValidity(''), 1800)
  }

  // Text fields too (phone, ID, codes, notes): Arabic-Indic digits from an Arabic
  // keyboard become 0-9 as typed, so numbers never show reversed or mixed.
  document.addEventListener('beforeinput', (e: InputEvent) => {
    const el = e.target as HTMLInputElement | HTMLTextAreaElement
    if (!(el instanceof HTMLTextAreaElement || el instanceof HTMLInputElement) || (el as HTMLInputElement).type === 'number' || !e.data) return
    if (!/[٠-٩۰-۹]/.test(e.data)) return
    e.preventDefault()
    document.execCommand('insertText', false, e.data.replace(/[٠-٩۰-۹]/g, d => String(AR.indexOf(d) % 10)))
  }, true)

  document.addEventListener('beforeinput', (e: InputEvent) => {
    const el = e.target as HTMLInputElement
    if (!(el instanceof HTMLInputElement) || el.type !== 'number' || !e.data) return
    const data = toLatin(e.data)
    if (!allowed.test(data)) { e.preventDefault(); warn(el); return }
    if (data !== e.data) {   // Arabic digit typed: insert the Latin one instead
      e.preventDefault()
      el.value = (el.value || '') + data
      el.dispatchEvent(new Event('input', { bubbles: true }))
    }
  }, true)

  document.addEventListener('paste', (e: ClipboardEvent) => {
    const el = e.target as HTMLInputElement
    if (!(el instanceof HTMLInputElement) || el.type !== 'number') return
    const text = toLatin(e.clipboardData?.getData('text') || '').trim()
    e.preventDefault()
    if (!allowed.test(text) || text === '') { warn(el); return }
    el.value = text
    el.dispatchEvent(new Event('input', { bubbles: true }))
  }, true)
})
