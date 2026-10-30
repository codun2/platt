# ركائز — Rakeez

منصّة الأعمال المتكاملة (Multi-Tenant modular business platform). نظام واحد لكل قطاع.

راجع [ARCHITECTURE.md](ARCHITECTURE.md) للمخطط الكامل (Core + المحرّكات + الباقات + السايدبار الديناميكي).

## الحالة
- المرحلة ۰: الواجهة الرئيسية + مخطط المعمارية ✅
- المرحلة ۱ (Core): Laravel API + Nuxt SPA + MySQL · Multi-Tenant · auth · أدوار وصلاحيات · سايدبار ديناميكي · لوحة Super-Admin ✅
- المرحلة ۲ (Inventory + POS): أصناف · مستودعات · مشتريات · حركات مخزون · عملاء · شاشة بيع · فواتير · ورديات · تقارير ✅
- المرحلة ۳ (Bookings + Travel): وحدات (قاعات/شاليهات/غرف) · تقويم · حجوزات بخدمات وتأمين ودفعات · رحلات حج/عمرة/سياحة · مسافرون · باقات · مصروفات ✅

## الهيكل
```
api/     Laravel 13 (PHP 8.4) — REST API على /api
web/     Nuxt 3 SPA (ssr:false) — تُبنى داخل صورة platt-web وتُخدَم على /app/
public/  صفحة الهبوط العامة (/)
```

## التشغيل
```bash
docker compose up -d --build        # http://localhost:8014
# platt-db (MySQL 8) · platt-api (artisan serve :8000) · platt-web (nginx :80 → 127.0.0.1:8014)
```
الـ API يشغّل migrations + seeders (idempotent) تلقائياً عند الإقلاع.

## الحسابات
- **مدير المنصّة:** `admin@rakeez.app` — كلمة المرور من `SUPER_ADMIN_PASSWORD` في `/opt/platt/.env` (غير مرفوع). غيّرها من زر المفتاح في الشريط العلوي.
- **حسابات التجربة** (`demo@rakeez.app`، `<sector>@test.app`) تُزرع فقط عندما `SEED_DEMO=true` (بيئة تجربة)؛ لا تُنشأ على الإنتاج.

## المسارات
- `/` صفحة الهبوط · `/app/login` · `/app/register` (اختيار القطاع → تفعيل وحدات الباقة)
- `/app/` لوحة المشترك (سايدبار مولّد من `modules` ∩ `tenant_modules` ∩ صلاحيات المستخدم)
- `/app/super` لوحة المنصّة: المشتركون، الوحدات، الباقات، الخطط، الاشتراكات
- `/app/pos` شاشة البيع · `/app/pos/sales` الفواتير · `/app/inventory/*` المخزون · `/app/crm/customers` العملاء
- `/app/bookings` الحجوزات · `/app/bookings/calendar` التقويم · `/app/travel` الرحلات والمسافرون

## النشر والتشغيل (إنتاج)
Contabo VPS، `/opt/platt`، منفذ داخلي `127.0.0.1:8014` خلف nginx (الدومين https://rekaz.smartunita.com عبر Cloudflare، و:8100 بالـIP)، auto-deploy عبر `deploy.sh` (cron كل دقيقة، يتوقف تلقائياً إذا كانت هناك تعديلات غير مرفوعة).

- **الأسرار:** `/opt/platt/.env` (انسخ `.env.example`) — كلمات مرور MySQL والمدير الأعلى؛ `api/.env` يحوي `APP_KEY` (لا تفقده: يفكّ تشفير البيانات).
- **النسخ الاحتياطي:** `backup.sh` يومياً 03:15 (cron) → `/opt/platt-backups/<تاريخ>/` (قاعدة البيانات + رفوعات + ملفات env). احتفاظ 14 يوماً، ونسخ الأحد 60 يوماً. **الاستعادة:** `restore.sh <dir> [db]`.
- **المراقبة:** `GET /api/health` (200 = التطبيق وقاعدة البيانات بخير).
- **اختبارات الحساسيات:** `scripts/smoke.sh` (عزل المستأجرين، الصلاحيات، توازن القيود، idempotency، كلمة المرور).
- التفاصيل الكاملة في `docs/PRODUCTION.md`.
