/**
 * Export the printable sheet (.doc-print) of an invoice / quote as a PDF file.
 * Rasterises the rendered DOM (html2canvas) so Arabic shaping, RTL and the
 * company letterhead come out exactly as on screen. Loaded lazily.
 */
export async function exportDocPdf(sheet: HTMLElement | null, filename: string) {
  if (!sheet) throw new Error(tt('لا توجد ورقة للتصدير', 'Nothing to export'))
  const { default: html2pdf } = await import('html2pdf.js')

  // Visible clone at A4 width (794px @ 96dpi) on top of the page while capturing.
  const wrap = document.createElement('div')
  wrap.setAttribute('dir', document.documentElement.dir || 'rtl')
  wrap.style.cssText = 'position:fixed;inset:0;z-index:99999;background:#fff;overflow:auto;padding:0'
  const clone = sheet.cloneNode(true) as HTMLElement
  clone.style.cssText = 'display:block;width:794px;margin:0 auto;padding:28px 32px;background:#fff;color:#111;box-sizing:border-box'
  const hint = document.createElement('div')
  hint.textContent = tt('جاري تصدير ملف PDF…', 'Exporting PDF…')
  hint.style.cssText = 'position:fixed;top:12px;left:50%;transform:translateX(-50%);background:#0f172a;color:#fff;padding:8px 16px;border-radius:999px;font-size:13px;z-index:100000'
  wrap.appendChild(clone); document.body.appendChild(wrap); document.body.appendChild(hint)

  try {
    await html2pdf().set({
      margin: [8, 8, 10, 8],
      filename,
      image: { type: 'jpeg', quality: 0.96 },
      html2canvas: { scale: 2, useCORS: true, backgroundColor: '#ffffff', windowWidth: 794 },
      jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
      pagebreak: { mode: ['css', 'legacy'], avoid: ['tr', '.dp-totals', '.dp-head'] },
    }).from(clone).save()
  } finally {
    wrap.remove(); hint.remove()
  }
}

/** Safe file name: "<number> - <company>.pdf" keeping Arabic letters intact. */
export function docPdfName(number: string, company?: string | null) {
  const clean = (s: string) => s.replace(/[\\/:*?"<>|]+/g, ' ').replace(/\s+/g, ' ').trim()
  return clean(`${number}${company ? ' - ' + company : ''}`) + '.pdf'
}
