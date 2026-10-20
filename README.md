# ركائز — Rakeez

منصّة الأعمال المتكاملة (Multi-Tenant modular business platform). نظام واحد لكل قطاع.

راجع [ARCHITECTURE.md](ARCHITECTURE.md) للمخطط الكامل (Core + المحرّكات + الباقات + السايدبار الديناميكي).

## الحالة
المرحلة ۰: الواجهة الرئيسية + مخطط المعمارية — جاهزة ومنشورة.

## التشغيل محلياً (المرحلة 0)
```bash
docker compose up -d --build   # http://localhost:8014
```

## النشر
Contabo VPS، `/opt/platt`، منفذ داخلي `127.0.0.1:8014` خلف nginx، auto-deploy (edit→push→live).
