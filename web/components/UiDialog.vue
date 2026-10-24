<script setup lang="ts">
/** Renders uiConfirm / uiAlert (see composables/useDialog.ts). */
const { dialog, close } = useDialogState()
const okBtn = ref<HTMLButtonElement | null>(null)
const icon = computed(() => ({ danger: 'ti-trash', warn: 'ti-alert-triangle', info: 'ti-info-circle' } as any)[dialog.value.tone || 'info'])
const title = computed(() => dialog.value.title || (dialog.value.kind === 'alert' ? tt('تنبيه', 'Notice') : dialog.value.tone === 'danger' ? tt('تأكيد الإجراء', 'Please confirm') : tt('تأكيد', 'Confirm')))
const okText = computed(() => dialog.value.okText || (dialog.value.kind === 'alert' ? tt('حسناً', 'OK') : dialog.value.tone === 'danger' ? tt('نعم، تأكيد', 'Yes, confirm') : tt('تأكيد', 'Confirm')))
watch(() => dialog.value.open, o => { if (o) nextTick(() => okBtn.value?.focus()) })
function onKey(e: KeyboardEvent) { if (!dialog.value.open) return; if (e.key === 'Escape') close(false) }
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <Transition name="dlg">
    <div v-if="dialog.open" class="dlg-bg" @click.self="close(false)">
      <div class="dlg" :class="dialog.tone" role="alertdialog" aria-modal="true">
        <div class="dlg-ic"><i :class="`ti ${icon}`" /></div>
        <h3>{{ title }}</h3>
        <p>{{ dialog.message }}</p>
        <div class="dlg-actions">
          <button v-if="dialog.kind === 'confirm'" class="btn ghost" @click="close(false)">{{ dialog.cancelText || tt('تراجع', 'Cancel') }}</button>
          <button ref="okBtn" class="btn ok-btn" @click="close(true)">{{ okText }}</button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.dlg-bg { position: fixed; inset: 0; z-index: 2000; background: rgba(15, 23, 42, .45); backdrop-filter: blur(3px); display: grid; place-items: center; padding: 16px; }
.dlg { width: 100%; max-width: 400px; background: var(--card); border-radius: 18px; box-shadow: 0 24px 60px rgba(15, 23, 42, .28); padding: 26px 24px 20px; text-align: center; }
.dlg-ic { width: 56px; height: 56px; border-radius: 50%; display: grid; place-items: center; margin: 0 auto 12px; font-size: 26px; background: #e0f2fe; color: #0369a1; }
.dlg.danger .dlg-ic { background: #fee2e2; color: #dc2626; }
.dlg.warn .dlg-ic { background: #fef3c7; color: #b45309; }
.dlg h3 { margin: 0 0 6px; font-size: 18px; }
.dlg p { margin: 0 0 20px; color: var(--muted); line-height: 1.7; white-space: pre-line; }
.dlg-actions { display: flex; gap: 10px; justify-content: center; }
.dlg-actions .btn { min-width: 120px; justify-content: center; padding: 10px 18px; }
.ok-btn { background: #0369a1; }
.ok-btn:hover { background: #075985; }
.dlg.danger .ok-btn { background: #dc2626; }
.dlg.danger .ok-btn:hover { background: #b91c1c; }
.dlg.warn .ok-btn { background: var(--primary); }
.dlg.warn .ok-btn:hover { background: var(--primary-600); }
.dlg-enter-active, .dlg-leave-active { transition: opacity .15s ease; }
.dlg-enter-active .dlg, .dlg-leave-active .dlg { transition: transform .18s ease; }
.dlg-enter-from, .dlg-leave-to { opacity: 0; }
.dlg-enter-from .dlg, .dlg-leave-to .dlg { transform: scale(.94) translateY(6px); }
</style>
