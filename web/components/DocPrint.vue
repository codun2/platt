<script setup lang="ts">
/** Printable A4 sheet for an invoice or quote. Hidden on screen, shown by @media print (see main.css). */
const props = defineProps<{ kind: DocKind; doc: any; company: any }>()
const f = useFmt(); const { tt } = useI18n()
const isInvoice = computed(() => props.kind === 'invoice')
const title = computed(() => isInvoice.value ? tt('فاتورة','INVOICE') : tt('عرض سعر','QUOTE'))
const st = computed(() => (isInvoice.value ? invoiceStatus : quoteStatus)[props.doc.status]?.label)
</script>

<template>
  <div class="print-only doc-print">
    <header class="dp-head">
      <div>
        <h1>{{ company?.name }}</h1>
        <div class="dp-meta">
          <span v-if="company?.address">{{ company.address }}</span>
          <span v-if="company?.phone" dir="ltr">{{ company.phone }}</span>
          <span v-if="company?.email" dir="ltr">{{ company.email }}</span>
          <span v-if="company?.tax_number">{{ tt('الرقم الضريبي','Tax No.') }}: {{ company.tax_number }}</span>
          <span v-if="company?.cr_number">{{ tt('س.ت','CR') }}: {{ company.cr_number }}</span>
        </div>
      </div>
      <div class="dp-title">
        <h2>{{ title }}</h2>
        <b dir="ltr">{{ doc.number }}</b>
        <small>{{ st }}</small>
      </div>
    </header>

    <section class="dp-grid">
      <div>
        <h4>{{ isInvoice ? tt('فاتورة إلى','Bill to') : tt('مقدّم إلى','Prepared for') }}</h4>
        <b>{{ doc.customer?.name || tt('عميل نقدي','Cash customer') }}</b>
        <div v-if="doc.customer?.company_name">{{ doc.customer.company_name }}</div>
        <div v-if="doc.customer?.phone" dir="ltr" style="text-align:right">{{ doc.customer.phone }}</div>
        <div v-if="doc.customer?.email" dir="ltr" style="text-align:right">{{ doc.customer.email }}</div>
        <div v-if="doc.customer?.address || doc.customer?.city">{{ [doc.customer.address, doc.customer.area, doc.customer.city].filter(Boolean).join('، ') }}</div>
        <div v-if="doc.customer?.tax_number">{{ tt('الرقم الضريبي','Tax No.') }}: {{ doc.customer.tax_number }}</div>
      </div>
      <div>
        <h4>{{ tt('التفاصيل','Details') }}</h4>
        <div class="row between"><span>{{ tt('التاريخ','Date') }}</span><b>{{ f.d(doc.date) }}</b></div>
        <div v-if="isInvoice && doc.due_date" class="row between"><span>{{ tt('تاريخ الاستحقاق','Due date') }}</span><b>{{ f.d(doc.due_date) }}</b></div>
        <div v-if="!isInvoice && doc.valid_until" class="row between"><span>{{ tt('صالح حتى','Valid until') }}</span><b>{{ f.d(doc.valid_until) }}</b></div>
        <div v-if="doc.reference" class="row between"><span>{{ tt('المرجع','Reference') }}</span><b dir="ltr">{{ doc.reference }}</b></div>
        <div v-if="doc.subject" class="row between"><span>{{ tt('الموضوع','Subject') }}</span><b>{{ doc.subject }}</b></div>
        <div v-if="isInvoice && doc.quote" class="row between"><span>{{ tt('عرض السعر','Quote') }}</span><b dir="ltr">{{ doc.quote.number }}</b></div>
      </div>
    </section>

    <table class="dp-table">
      <thead><tr><th style="width:30px">#</th><th>{{ tt('الوصف','Description') }}</th><th style="width:70px">{{ tt('الكمية','Qty') }}</th><th style="width:60px">{{ tt('الوحدة','Unit') }}</th><th style="width:100px">{{ tt('سعر الوحدة','Unit price') }}</th><th style="width:80px">{{ tt('خصم','Disc.') }}</th><th style="width:110px">{{ tt('الإجمالي','Total') }}</th></tr></thead>
      <tbody>
        <tr v-for="(l, i) in doc.items" :key="l.id"><td>{{ i + 1 }}</td><td>{{ l.description }}</td><td class="num">{{ f.qty(l.qty) }}</td><td>{{ l.unit || '' }}</td><td class="num">{{ f.money(l.unit_price) }}</td><td class="num">{{ +l.discount ? f.money(l.discount) : '' }}</td><td class="num">{{ f.money(l.total) }}</td></tr>
      </tbody>
    </table>

    <section class="dp-bottom">
      <div class="dp-notes">
        <div v-if="doc.notes"><h4>{{ tt('ملاحظات','Notes') }}</h4><p>{{ doc.notes }}</p></div>
        <div v-if="!isInvoice && doc.terms"><h4>{{ tt('الشروط','Terms') }}</h4><p>{{ doc.terms }}</p></div>
        <div v-if="isInvoice && company?.invoice_terms"><h4>{{ tt('شروط السداد','Payment terms') }}</h4><p>{{ company.invoice_terms }}</p></div>
        <div v-if="company?.bank_details"><h4>{{ tt('بيانات التحويل البنكي','Bank details') }}</h4><p>{{ company.bank_details }}</p></div>
      </div>
      <div class="dp-totals">
        <div class="row between"><span>{{ tt('المجموع','Subtotal') }}</span><b>{{ f.money(doc.subtotal) }}</b></div>
        <div v-if="+doc.discount" class="row between"><span>{{ tt('الخصم','Discount') }}</span><b>- {{ f.money(doc.discount) }}</b></div>
        <div class="row between"><span>{{ tt('الضريبة','Tax') }} ({{ doc.tax_rate }}%)</span><b>{{ f.money(doc.tax) }}</b></div>
        <div class="row between total"><span>{{ tt('الإجمالي','Total') }}</span><b>{{ f.money(doc.total, true) }}</b></div>
        <template v-if="isInvoice">
          <div class="row between"><span>{{ tt('المدفوع','Paid') }}</span><b>{{ f.money(doc.paid) }}</b></div>
          <div class="row between" :class="{ due: +doc.balance > 0 }"><span>{{ tt('المتبقي','Balance') }}</span><b>{{ f.money(doc.balance, true) }}</b></div>
        </template>
      </div>
    </section>

    <footer class="dp-foot">{{ company?.invoice_footer || tt('شكراً لتعاملكم معنا','Thank you for your business') }}</footer>
  </div>
</template>
