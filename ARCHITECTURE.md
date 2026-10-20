# ركائز (Rakeez) — المعمارية والخطة

منصّة أعمال **Multi-Tenant modular**: أساس واحد + محرّكات متخصّصة، تُركّب في **باقات حسب النشاط**. نظام واحد، قاعدة واحدة، سايدبار مولّد من الداتابيز.

**Stack:** Laravel (API) + Nuxt (SPA) + MySQL + Docker. نمط النشر: [[contabo-new-vps]] auto-deploy.

---

## 1) المبدأ الأساسي: السايدبار مولّد من الداتابيز

لا قوائم ثابتة بالكود. القائمة الجانبية تُبنى بحلقة على جدول `modules` مفلترة بـ:
`(الوحدة مفعّلة لهذا المستأجر) AND (للمستخدم صلاحية رؤيتها)`.

## 2) نموذج البيانات (Core)

- **tenants** — المشتركون (الشركات). كل جدول أعمال يحمل `tenant_id` + Global Scope للعزل (نفس مبدأ `clinic_id` في [[vet-clinic-site]]).
- **users** — مستخدمو كل مستأجر + `super_admin` للمنصّة.
- **modules** — سجل كل الوحدات: `key, name_ar, name_en, icon, route, parent_key, engine, sort`.
- **tenant_modules** — أي وحدة مفعّلة لأي مستأجر (on/off).
- **sector_presets** — القطاعات (باقات): `key, name_ar, icon`.
- **preset_modules** — أي وحدات تُفعّل تلقائياً لكل باقة.
- **roles / permissions / role_user** — الصلاحيات داخل المستأجر.

التسجيل: المستأجر يختار `sector_preset` → النظام ينسخ `preset_modules` إلى `tenant_modules`.

## 3) المحرّكات (Engines) والقطاعات

| المحرّك | القطاعات | أعيد استخدامه من |
|---|---|---|
| **Core (أفقي)** | أعمال عامة · محاسبة شركات · عملاء وفواتير · HR | — |
| **Inventory + POS** | متاجر/POS · مبيعات ومخازن · معارض سيارات · حلوى (محلات) · خياطة | galary |
| **Bookings (تقويم)** | فنادق · شاليهات · قاعات ومناسبات · حج وعمرة وسفر | — |
| **Rental + عقود + أقساط** | تأجير سيارات · عقارات وأملاك | recar + عقارات |
| **Service Tickets** | ورش صيانة · مغاسل · صيانة | autogarage + coolcare |
| **Projects / Cases** | محاماة (قضايا) · استشارات هندسية | — |
| **Education** | مدارس ورياض أطفال | schoole |
| **Manufacturing** | حلوى (مصانع) · تصنيع | — |

## 4) خطة المراحل

- **المرحلة 0 (الحالية):** واجهة رئيسية + هذا المخطط + حاوية منشورة. ✅
- **المرحلة 1 — Core:** Laravel+Nuxt scaffold · tenants + عزل tenant_id · auth · super-admin · جداول modules/tenant_modules/presets · **سايدبار ديناميكي** · صلاحيات. ✅
- **المرحلة 2 — محرّك Inventory + POS:** أصناف · مخزون/مستودعات · فواتير بيع · POS. (يفتح 5 قطاعات) ✅
- **المرحلة 3+:** محرّك تلو الآخر حسب الأولوية (Bookings / Rental / Service / Education ...).

## التشغيل محلياً
`docker compose up -d --build` — راجع README.md للحسابات الافتراضية والمسارات.
