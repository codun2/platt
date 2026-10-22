/**
 * Translation dictionary (ar is the source of truth, en is the translation).
 * Keyed by namespace. Access with t('ns.key'). Grows per module.
 */
export const messages: Record<'ar' | 'en', Record<string, any>> = {
  ar: {
    app: { name: 'ركائز', tagline: 'منصّة الأعمال المتكاملة', brandSub: 'ركائز — منصّة الأعمال', platformAdmin: 'إدارة المنصّة' },
    common: {
      save: 'حفظ', saveChanges: 'حفظ التعديلات', cancel: 'إلغاء', edit: 'تعديل', delete: 'حذف', add: 'إضافة', create: 'إنشاء',
      search: 'بحث', close: 'إغلاق', back: 'رجوع', print: 'طباعة', exportPdf: 'تصدير PDF', details: 'التفاصيل', actions: 'الإجراءات',
      status: 'الحالة', active: 'نشط', inactive: 'موقوف', yes: 'نعم', no: 'لا', loading: 'جاري التحميل…', all: 'الكل', none: '—',
      new: 'جديد', total: 'الإجمالي', subtotal: 'المجموع', discount: 'الخصم', tax: 'الضريبة', date: 'التاريخ', notes: 'ملاحظات',
      name: 'الاسم', phone: 'الهاتف', email: 'البريد', address: 'العنوان', amount: 'المبلغ', paid: 'المدفوع', remaining: 'المتبقي',
      from: 'من', to: 'إلى', next: 'التالي', prev: 'السابق', page: 'صفحة', of: 'من', reference: 'المرجع', type: 'النوع',
      description: 'الوصف', createdBy: 'أنشأها', confirmDelete: 'تأكيد الحذف؟', noResults: 'لا توجد نتائج.', saved: 'تم الحفظ',
      saveAndNew: 'حفظ وإضافة آخر', logout: 'تسجيل الخروج', filter: 'تصفية', code: 'الكود', city: 'المدينة', notFound: 'غير موجود',
    },
    nav: { search: 'بحث في القائمة...' },
    locale: { switchTo: 'English', ar: 'العربية', en: 'الإنجليزية' },
    footer: { platform: 'ركائز — منصّة الأعمال المتكاملة', trial: 'فترة تجريبية', active: 'اشتراك فعّال', renews: 'يتجدد' },
    auth: {
      login: 'تسجيل الدخول', register: 'حساب جديد', email: 'البريد الإلكتروني', password: 'كلمة المرور', loginBtn: 'دخول',
      welcome: 'أهلاً {name}، مرحباً بعودتك', company: 'اسم الشركة', sector: 'القطاع', yourName: 'اسمك', createAccount: 'إنشاء الحساب',
      haveAccount: 'لديك حساب؟', noAccount: 'ليس لديك حساب؟', loggingIn: 'جاري الدخول…',
    },
    dashboard: {
      users: 'المستخدمون', activeModules: 'الوحدات المفعّلة', roles: 'الأدوار', renewDate: 'تاريخ التجديد', noPlan: 'بدون خطة',
      todaySales: 'مبيعات اليوم', monthSales: 'مبيعات الشهر', invoices: 'فاتورة', unpaid: 'مستحقات غير مسددة', lowStock: 'أصناف تحت الحد الأدنى',
    },
    locked: { title: 'هذه الصفحة', msg: 'هذه الوحدة مفعّلة لحسابك وستتوفر شاشاتها في المرحلة القادمة (محرّك {engine}).', back: 'العودة للرئيسية' },
  },
  en: {
    app: { name: 'Rakeez', tagline: 'All-in-one business platform', brandSub: 'Rakeez — Business Platform', platformAdmin: 'Platform Admin' },
    common: {
      save: 'Save', saveChanges: 'Save changes', cancel: 'Cancel', edit: 'Edit', delete: 'Delete', add: 'Add', create: 'Create',
      search: 'Search', close: 'Close', back: 'Back', print: 'Print', exportPdf: 'Export PDF', details: 'Details', actions: 'Actions',
      status: 'Status', active: 'Active', inactive: 'Inactive', yes: 'Yes', no: 'No', loading: 'Loading…', all: 'All', none: '—',
      new: 'New', total: 'Total', subtotal: 'Subtotal', discount: 'Discount', tax: 'Tax', date: 'Date', notes: 'Notes',
      name: 'Name', phone: 'Phone', email: 'Email', address: 'Address', amount: 'Amount', paid: 'Paid', remaining: 'Remaining',
      from: 'From', to: 'To', next: 'Next', prev: 'Previous', page: 'Page', of: 'of', reference: 'Reference', type: 'Type',
      description: 'Description', createdBy: 'Created by', confirmDelete: 'Confirm delete?', noResults: 'No results.', saved: 'Saved',
      saveAndNew: 'Save & add another', logout: 'Log out', filter: 'Filter', code: 'Code', city: 'City', notFound: 'Not found',
    },
    nav: { search: 'Search menu...' },
    locale: { switchTo: 'العربية', ar: 'Arabic', en: 'English' },
    footer: { platform: 'Rakeez — Integrated Business Platform', trial: 'Trial period', active: 'Active subscription', renews: 'Renews' },
    auth: {
      login: 'Sign in', register: 'New account', email: 'Email', password: 'Password', loginBtn: 'Sign in',
      welcome: 'Welcome back, {name}', company: 'Company name', sector: 'Sector', yourName: 'Your name', createAccount: 'Create account',
      haveAccount: 'Have an account?', noAccount: 'No account?', loggingIn: 'Signing in…',
    },
    dashboard: {
      users: 'Users', activeModules: 'Active modules', roles: 'Roles', renewDate: 'Renewal date', noPlan: 'No plan',
      todaySales: "Today's sales", monthSales: 'Month sales', invoices: 'invoices', unpaid: 'Unpaid balances', lowStock: 'Items below minimum',
    },
    locked: { title: 'This page', msg: 'This module is enabled for your account; its screens arrive in the next phase ({engine} engine).', back: 'Back to home' },
  },
}
