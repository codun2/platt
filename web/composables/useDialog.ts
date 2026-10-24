/**
 * Modern replacement for window.confirm / window.alert.
 *   if (!(await uiConfirm('حذف العميل؟'))) return
 *   uiAlert(e.message)
 * One dialog at a time, rendered by <UiDialog /> in app.vue. The tone (danger /
 * warning / info) is inferred from the wording unless passed explicitly.
 */
export interface DialogOpts { title?: string; okText?: string; cancelText?: string; tone?: 'danger' | 'warn' | 'info' }
interface DialogState extends DialogOpts { open: boolean; kind: 'confirm' | 'alert'; message: string; resolve?: (v: boolean) => void }

const _dialog = ref<DialogState>({ open: false, kind: 'confirm', message: '' })

const toneOf = (msg: string): DialogOpts['tone'] =>
  /حذف|إلغاء|فسخ|Delete|Cancel|Terminate/i.test(msg) ? 'danger' : /إغلاق|إنهاء|Close|End/i.test(msg) ? 'warn' : 'info'

function open(kind: 'confirm' | 'alert', message: string, opts: DialogOpts = {}): Promise<boolean> {
  _dialog.value.resolve?.(false)   // a newer dialog cancels a pending one
  return new Promise(resolve => {
    _dialog.value = { open: true, kind, message: String(message ?? ''), tone: opts.tone || (kind === 'alert' ? 'info' : toneOf(String(message))), ...opts, resolve }
  })
}

export const uiConfirm = (message: string, opts?: DialogOpts) => open('confirm', message, opts)
export const uiAlert = (message: string, opts?: DialogOpts) => open('alert', message, { tone: 'warn', ...opts })

export function useDialogState() {
  const close = (v: boolean) => { const r = _dialog.value.resolve; _dialog.value = { ..._dialog.value, open: false, resolve: undefined }; r?.(v) }
  return { dialog: _dialog, close }
}
