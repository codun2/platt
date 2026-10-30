# ركائز — دليل الإنتاج والتشغيل

## البنية
- `platt-db` MySQL 8 (volume `platt_platt-db-data`) · `platt-api` Laravel (PHP 8.4 built-in server، 8 workers) · `platt-web` nginx يقدّم الواجهة ويمرّر `/api` و`/storage` إلى الـAPI.
- الدومين: Cloudflare → nginx المضيف (`/etc/nginx/sites-available/rekaz-domain`, HSTS) → `127.0.0.1:8014`.

## الأسرار (لا تُرفع إلى Git)
| الملف | المحتوى |
|---|---|
| `/opt/platt/.env` | `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `SUPER_ADMIN_PASSWORD`, `APP_URL`, `SEED_DEMO`, `LOG_LEVEL` — يقرأه docker compose |
| `/opt/platt/api/.env` | `APP_KEY` + إعدادات Laravel (يُحدَّث جزئياً عند إقلاع الحاوية من متغيرات compose) |

تدوير كلمة مرور MySQL: `ALTER USER` داخل `platt-db` ثم حدّث `.env` و`docker compose up -d`.
تدوير كلمة مرور المدير الأعلى: من الواجهة (زر المفتاح) أو `php artisan tinker` داخل `platt-api`.

## النشر
`deploy.sh` (cron كل دقيقة): يسحب `origin/main` عند تغيّره، يبني الصور، يشغّل migrations وseeders (الأساسية فقط؛ حسابات التجربة تحتاج `SEED_DEMO=true`). يتوقف إذا كان هناك تعديل غير مرفوع في `/opt/platt`.

## النسخ الاحتياطي والاستعادة
- `backup.sh` — cron يومي 03:15. المخرجات في `/opt/platt-backups/<YYYY-MM-DD_HHMM>/`: `platt.sql.gz` (mysqldump، single-transaction)، `storage.tgz` (الرفوعات)، `api.env` + `compose.env`، `MANIFEST`. السجل: `/opt/platt-backups/backup.log`.
- الاحتفاظ: 14 يوماً لليومي، نسخ الأحد 60 يوماً.
- اختبار استعادة دون لمس الإنتاج: `restore.sh /opt/platt-backups/<dir> platt_restore_test` ثم `DROP DATABASE`.
- استعادة فعلية: `restore.sh /opt/platt-backups/<dir>` (يطلب كتابة RESTORE).
- **يُنصح** بنسخ `/opt/platt-backups` خارج الخادم (rclone إلى S3/Drive) — لم يُضبط بعد لعدم توفر بيانات اعتماد.

## المراقبة والسجلات
- `GET /api/health` → `{ok:true, db:true}`؛ اربطه بمراقب خارجي (UptimeRobot…).
- سجلات Laravel: `api/storage/logs/laravel-YYYY-MM-DD.log` (daily, warning+). سجل النشر: `deploy.log` (محدود 3000 سطر).

## الأمان المطبَّق
- الأسرار خارج الريبو (الريبو عام) وتم تدويرها عند التجهيز للإنتاج.
- `APP_DEBUG=false`، رسائل أخطاء بلا stack traces.
- حدود معدّل: تسجيل الدخول/التسجيل، صفحة الطلب العامة (تصفح/طلب منفصلان).
- رؤوس أمان من nginx (nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy) + HSTS على الدومين.
- تغيير كلمة المرور يتطلب كلمة المرور الحالية.
- عزل المستأجرين عبر global scope + فحص الوحدات المفعّلة في الـmiddleware (`scripts/smoke.sh` يتحقق).

## توصيات لاحقة
- استبدال خادم PHP المدمج بـ php-fpm + nginx (أو FrankenPHP) عند نمو الحمل.
- نسخ احتياطي خارجي (off-site) + تنبيه عند فشل النسخ.
- مراجعة حسابات التجربة على الإنتاج (تعطيلها أو تغيير كلمات مرورها من لوحة المنصّة) قبل فتح التسجيل للعموم.
