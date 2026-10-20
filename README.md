# ركائز — Rakeez

منصّة الأعمال المتكاملة (Multi-Tenant modular business platform). نظام واحد لكل قطاع.

راجع [ARCHITECTURE.md](ARCHITECTURE.md) للمخطط الكامل (Core + المحرّكات + الباقات + السايدبار الديناميكي).

## الحالة
- المرحلة ۰: الواجهة الرئيسية + مخطط المعمارية ✅
- المرحلة ۱ (Core): Laravel API + Nuxt SPA + MySQL · Multi-Tenant · auth · أدوار وصلاحيات · سايدبار ديناميكي · لوحة Super-Admin ✅
- المرحلة ۲ (Inventory + POS): أصناف · مستودعات · مشتريات · حركات مخزون · عملاء · شاشة بيع · فواتير · ورديات · تقارير ✅

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

## حسابات افتراضية
| الدور | البريد | كلمة المرور |
|---|---|---|
| Super-Admin | admin@rakeez.app | Rakeez@2026 (من `SUPER_ADMIN_PASSWORD`) |
| شركة تجريبية (قطاع متاجر) | demo@rakeez.app | demo1234 |

## المسارات
- `/` صفحة الهبوط · `/app/login` · `/app/register` (اختيار القطاع → تفعيل وحدات الباقة)
- `/app/` لوحة المشترك (سايدبار مولّد من `modules` ∩ `tenant_modules` ∩ صلاحيات المستخدم)
- `/app/super` لوحة المنصّة: المشتركون، الوحدات، الباقات، الخطط، الاشتراكات
- `/app/pos` شاشة البيع · `/app/pos/sales` الفواتير · `/app/inventory/*` المخزون · `/app/crm/customers` العملاء

## النشر
Contabo VPS، `/opt/platt`، منفذ داخلي `127.0.0.1:8014` خلف nginx (:8100)، auto-deploy (edit→push→live) عبر `deploy.sh` (cron كل دقيقة).
