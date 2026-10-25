<script setup lang="ts">
/** Pre-order form: who, when, pickup / delivery, items, deposit (new only). */
const props = defineProps<{ form: any; customers: any[]; isNew?: boolean }>()
const auth = useAuth(); const f = useFmt()
const walkIn = ref(!props.form.customer_id)
watch(walkIn, v => { if (v) props.form.customer_id = ''; else { props.form.customer_name = ''; props.form.customer_phone = '' } })
const customer = computed(() => props.customers.find((c: any) => String(c.id) === String(props.form.customer_id)))
// A registered customer's address fills the delivery address.
watch(() => props.form.customer_id, () => { if (customer.value?.address && !props.form.address) props.form.address = customer.value.address })
const taxRate = computed(() => +(auth.state.value.tenant?.tax_rate || 0))
const totals = computed(() => preOrderTotals(props.form, taxRate.value))
const canPickItems = computed(() => auth.hasModule('inventory'))
const presets: [string, string][] = [['كيكة حسب الطلب', 'Custom cake'], ['صينية حلويات مشكّلة', 'Mixed sweets tray'], ['تغليف هدية', 'Gift wrapping'], ['كتابة على الكيك', 'Writing on cake']]
</script>

<template>
  <div>
    <div class="card-title"><i class="ti ti-user" /> {{ tt('صاحب الطلب', 'Customer') }}</div>
    <div class="row" style="gap:6px;margin-bottom:8px"><span class="chip" :class="{ on: walkIn }" @click="walkIn = true">{{ tt('اسم وجوال فقط', 'Name & phone only') }}</span><span class="chip" :class="{ on: !walkIn }" @click="walkIn = false">{{ tt('عميل مسجّل', 'Registered customer') }}</span></div>
    <div class="grid grid-4">
      <template v-if="walkIn">
        <div class="field"><label>{{ tt('الاسم', 'Name') }} <span style="color:var(--primary)">*</span></label><input v-model="form.customer_name" /></div>
        <div class="field"><label>{{ tt('الجوال', 'Mobile') }}</label><input v-model="form.customer_phone" dir="ltr" placeholder="05xxxxxxxx" /></div>
      </template>
      <div v-else class="field" style="grid-column:span 2"><label>{{ tt('العميل', 'Customer') }} <span style="color:var(--primary)">*</span></label><UiSelect v-model="form.customer_id" :placeholder="tt('اختر', 'Select')" :add-label="tt('إضافة عميل', 'Add customer')" add-to="/crm/customers/new" add-key="customer"><option value="">—</option><option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}<template v-if="c.phone"> — {{ c.phone }}</template></option></UiSelect></div>
      <div class="field"><label>{{ tt('وصل الطلب عبر', 'Received via') }}</label><UiSelect v-model="form.channel"><option v-for="(c, k) in preOrderChannel" :key="k" :value="k">{{ c.label }}</option></UiSelect></div>
      <div class="field"><label>{{ tt('موعد التسليم', 'Due') }} <span style="color:var(--primary)">*</span></label><input v-model="form.due_at" type="datetime-local" dir="ltr" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-truck-delivery" /> {{ tt('الاستلام', 'Fulfilment') }}</div>
    <div class="row" style="gap:6px;margin-bottom:8px">
      <span class="chip" :class="{ on: form.fulfilment === 'pickup' }" @click="form.fulfilment = 'pickup'"><i class="ti ti-building-store" /> {{ tt('يستلم من المحل', 'Pickup in store') }}</span>
      <span class="chip" :class="{ on: form.fulfilment === 'delivery' }" @click="form.fulfilment = 'delivery'"><i class="ti ti-truck-delivery" /> {{ tt('توصيل', 'Delivery') }}</span>
    </div>
    <div v-if="form.fulfilment === 'delivery'" class="grid grid-4">
      <div class="field" style="grid-column:span 2"><label>{{ tt('عنوان التوصيل', 'Delivery address') }} <span style="color:var(--primary)">*</span></label><input v-model="form.address" :placeholder="tt('الحي، الشارع، أقرب معلم، رابط الموقع', 'District, street, landmark, map link')" /></div>
      <div class="field"><label>{{ tt('رسوم التوصيل', 'Delivery fee') }}</label><input v-model.number="form.delivery_fee" type="number" min="0" step="0.001" dir="ltr" /></div>
      <div class="field"><label>{{ tt('السائق / المندوب', 'Driver') }}</label><input v-model="form.driver" /></div>
    </div>

    <div class="card-title" style="margin-top:8px"><i class="ti ti-list-details" /> {{ tt('محتوى الطلب', 'Items') }}</div>
    <DocLinesEditor :lines="form.lines" :can-pick-items="canPickItems" mode="items" :desc-placeholder="tt('الصنف والتفاصيل (الحجم، النكهة، الكتابة…)', 'Item & details (size, flavour, writing…)')" :presets="presets" />

    <div class="grid grid-2" style="margin-top:16px;align-items:start">
      <div>
        <div class="field"><label>{{ tt('ملاحظات للفريق والعميل', 'Notes for the team / customer') }}</label><textarea v-model="form.notes" rows="3" :placeholder="tt('مثال: بدون مكسرات، شمعة رقم 7، التغليف أحمر', 'e.g. no nuts, candle no. 7, red wrapping')" /></div>
        <div class="grid grid-2">
          <div class="field"><label>{{ tt('نوع الخصم', 'Discount type') }}</label><UiSelect v-model="form.discount_type"><option value="amount">{{ tt('مبلغ', 'Amount') }}</option><option value="percent">{{ tt('نسبة %', 'Percent %') }}</option></UiSelect></div>
          <div class="field"><label>{{ tt('قيمة الخصم', 'Discount') }}</label><input v-model.number="form.discount_value" type="number" min="0" step="0.001" dir="ltr" /></div>
        </div>
        <template v-if="isNew">
          <div class="card-title" style="margin-top:4px"><i class="ti ti-cash" /> {{ tt('العربون (اختياري)', 'Deposit (optional)') }}</div>
          <div class="grid grid-2">
            <div class="field"><label>{{ tt('المبلغ المستلم الآن', 'Amount received now') }}</label><input v-model.number="form.deposit" type="number" min="0" step="0.001" dir="ltr" :placeholder="f.money(totals.total / 2)" /></div>
            <div class="field"><label>{{ tt('الطريقة', 'Method') }}</label><UiSelect v-model="form.deposit_method"><option value="cash">{{ tt('نقدي', 'Cash') }}</option><option value="card">{{ tt('بطاقة', 'Card') }}</option><option value="transfer">{{ tt('تحويل', 'Transfer') }}</option></UiSelect></div>
          </div>
        </template>
      </div>
      <div class="totals-box">
        <div class="row between"><span>{{ tt('المجموع', 'Subtotal') }}</span><b>{{ f.money(totals.subtotal) }}</b></div>
        <div v-if="totals.discount" class="row between"><span>{{ tt('الخصم', 'Discount') }}</span><b>- {{ f.money(totals.discount) }}</b></div>
        <div v-if="totals.fee" class="row between"><span>{{ tt('التوصيل', 'Delivery') }}</span><b>{{ f.money(totals.fee) }}</b></div>
        <div class="row between"><span>{{ tt('الضريبة', 'Tax') }} ({{ taxRate }}%)</span><b>{{ f.money(totals.tax) }}</b></div>
        <div class="row between total"><span>{{ tt('الإجمالي', 'Total') }}</span><b>{{ f.money(totals.total, true) }}</b></div>
        <template v-if="isNew && +form.deposit > 0">
          <div class="row between" style="color:var(--ok)"><span>{{ tt('العربون', 'Deposit') }}</span><b>- {{ f.money(form.deposit) }}</b></div>
          <div class="row between"><span>{{ tt('المتبقي عند التسليم', 'Due on handover') }}</span><b>{{ f.money(Math.max(0, totals.total - form.deposit)) }}</b></div>
        </template>
      </div>
    </div>
  </div>
</template>
