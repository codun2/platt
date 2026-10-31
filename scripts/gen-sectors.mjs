// Generates a static marketing landing page per sector into public/sectors/,
// and rewrites the sector grid on the homepage (public/index.html) so each card
// links to its page. Single source of truth = the SECTORS array below.
// Run: node scripts/gen-sectors.mjs   (from repo root)
import { writeFileSync, readFileSync, mkdirSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..')
const OUT = join(ROOT, 'public', 'sectors')
mkdirSync(OUT, { recursive: true })

const COLORS = ['bg-blue', 'bg-emerald', 'bg-amber', 'bg-violet', 'bg-rose', 'bg-blue']

// Shared FAQ (appended after each sector's own 1–2 questions).
const SHARED_FAQ = (s) => [
  { q: 'هل أحتاج خبرة تقنية لتشغيل النظام؟', a: 'لا. الواجهة عربية بالكامل وبسيطة، والوحدات تتفعّل تلقائياً حسب نشاطك، فتبدأ العمل خلال دقائق بدون إعداد معقّد.' },
  { q: 'هل بياناتي آمنة؟', a: 'نعم. كل مشترك بياناته معزولة تماماً عن غيره (Multi-Tenant)، مع صلاحيات دقيقة لكل مستخدم ونسخ احتياطي دوري.' },
  { q: 'هل يدعم النظام الفاتورة الإلكترونية (ZATCA)؟', a: 'نعم. يصدر فواتير ضريبية متوافقة مع المرحلة الأولى لهيئة الزكاة والضريبة برمز QR جاهز للطباعة.' },
  { q: 'هل أقدر أغيّر الباقة لاحقاً؟', a: 'بالتأكيد. يمكنك الترقية أو التخفيض في أي وقت، وتُحتسب الفروق تلقائياً.' },
  { q: 'كم يستغرق تفعيل الحساب؟', a: `دقائق فقط: تسجّل حسابك، تختار قطاع "${s.card}"، وتبدأ مباشرة.` },
  { q: 'هل يوجد عقد أو التزام طويل؟', a: 'لا. الاشتراك شهري مرن، وتبدأ بتجربة مجانية قبل أي دفع.' },
]

const esc = (x) => String(x)

function planList(items) {
  return items.map((t) => `            <li><span class="ck">✓</span> ${esc(t)}</li>`).join('\n')
}

function cmpRows(s) {
  const yes = '<td class="yes">✓</td>'
  const no = '<td class="no">—</td>'
  const rows = []
  rows.push(`<tr><td>عدد المستخدمين</td><td><b>3</b></td><td><b>10</b></td><td><b>50</b></td></tr>`)
  rows.push(`<tr><td>الفروع / الوحدات</td><td>واحد</td><td>حتى 3</td><td>غير محدود</td></tr>`)
  for (const c of s.core) rows.push(`<tr><td>${esc(c)}</td>${yes}${yes}${yes}</tr>`)
  rows.push(`<tr><td>الفواتير الضريبية (ZATCA)</td>${yes}${yes}${yes}</tr>`)
  for (const p of s.pro) rows.push(`<tr><td>${esc(p)}</td>${no}${yes}${yes}</tr>`)
  rows.push(`<tr><td>الموارد البشرية والرواتب</td>${no}${yes}${yes}</tr>`)
  rows.push(`<tr><td>التقارير</td><td>أساسية</td><td>متقدمة</td><td>كاملة + تحليلات</td></tr>`)
  rows.push(`<tr><td>النسخ الاحتياطي</td><td>يومي</td><td>يومي</td><td>لحظي</td></tr>`)
  rows.push(`<tr><td>الدعم الفني</td><td>بريد</td><td>أولوية</td><td>مدير حساب مخصّص</td></tr>`)
  return rows.map((r) => `            ${r}`).join('\n')
}

function page(s) {
  const basic = ['حتى 3 مستخدمين', 'وحدة / فرع واحد', ...s.core, 'الفواتير الضريبية (ZATCA)', 'تقارير أساسية', 'نسخ احتياطي يومي', 'دعم عبر البريد']
  const pro = ['حتى 10 مستخدمين', 'حتى 3 فروع / وحدات', 'كل مزايا الأساسية', ...s.pro, 'الموارد البشرية والرواتب', 'تقارير متقدمة', 'دعم بأولوية']
  const ent = ['حتى 50 مستخدم', 'وحدات وفروع غير محدودة', 'كل مزايا الاحترافية', 'تحليلات ولوحات متقدمة', 'صلاحيات وأدوار متقدمة', 'نسخ احتياطي لحظي', 'تكاملات وواجهة برمجية (API)', 'مدير حساب مخصّص']
  const feats = s.features.map((f, i) => `        <div class="feat">
          <div class="fi ${COLORS[i % COLORS.length]}">${f.ic}</div>
          <h3>${esc(f.h)}</h3>
          <p>${esc(f.p)}</p>
        </div>`).join('\n')
  const chips = s.chips.map((c) => `        <span class="chip"><span class="d"></span>${esc(c)}</span>`).join('\n')
  const faqs = [...(s.faq || []), ...SHARED_FAQ(s)]
  const faqHtml = faqs.map((f, i) => `        <details${i === 0 ? ' open' : ''}><summary>${esc(f.q)}</summary><p>${esc(f.a)}</p></details>`).join('\n')

  return `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ركائز — حلول ${esc(s.card)}</title>
  <meta name="description" content="${esc(s.lead)} باقات تبدأ من 15 ريال شهرياً." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet" />
  <style>
    :root{
      --blue:#2563eb; --blue-dark:#1d4ed8; --navy:#0f172a;
      --emerald:#10b981; --amber:#f59e0b; --violet:#7c3aed; --rose:#f43f5e;
      --ink:#0f172a; --muted:#64748b; --bg:#f1f5f9; --card:#ffffff;
      --ring:rgba(37,99,235,.1); --shadow:0 18px 40px -18px rgba(15,23,42,.22);
    }
    *{box-sizing:border-box;margin:0;padding:0}
    html{scroll-behavior:smooth}
    body{font-family:'Cairo',system-ui,sans-serif;background:var(--bg);color:var(--ink);line-height:1.7;-webkit-font-smoothing:antialiased}
    a{text-decoration:none;color:inherit}
    .wrap{max-width:1180px;margin:0 auto;padding:0 24px}
    header{position:sticky;top:0;z-index:50;background:rgba(241,245,249,.82);backdrop-filter:blur(14px);border-bottom:1px solid rgba(15,23,42,.06)}
    nav{display:flex;align-items:center;justify-content:space-between;height:74px;gap:16px}
    .brand{display:flex;align-items:center;gap:12px;font-weight:900;font-size:23px}
    .logo{width:44px;height:44px;border-radius:13px;background:linear-gradient(135deg,var(--blue),#60a5fa);display:grid;place-items:center;color:#fff;box-shadow:0 8px 20px -6px var(--blue)}
    .nav-links{display:flex;gap:30px;align-items:center;font-weight:600;color:var(--muted)}
    .nav-links a:hover{color:var(--blue)}
    .btn{display:inline-flex;align-items:center;gap:8px;padding:12px 22px;border-radius:12px;font-weight:700;font-family:inherit;cursor:pointer;border:none;transition:.2s;font-size:15px}
    .btn-primary{background:var(--blue);color:#fff;box-shadow:0 10px 24px -10px var(--blue)}
    .btn-primary:hover{background:var(--blue-dark);transform:translateY(-2px)}
    .btn-ghost{background:transparent;color:var(--ink)}
    .btn-ghost:hover{background:var(--ring)}
    @media(max-width:860px){.nav-links{display:none}}
    .svg{width:26px;height:26px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    section{padding:66px 0}
    .sec-head{text-align:center;max-width:720px;margin:0 auto 46px}
    .pill{display:inline-flex;align-items:center;gap:8px;background:var(--ring);color:var(--blue);font-weight:700;font-size:14px;padding:8px 16px;border-radius:999px;margin-bottom:16px}
    .sec-head h2{font-size:clamp(28px,4vw,40px);font-weight:900;letter-spacing:-.5px}
    .sec-head p{color:var(--muted);font-size:17px;margin-top:14px}
    .back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);font-weight:700;font-size:14px}
    .back:hover{color:var(--blue)}
    .hero-sector{padding:56px 0 40px;text-align:center}
    .sector-badge{width:90px;height:90px;border-radius:24px;display:inline-grid;place-items:center;font-size:46px;background:#fff;box-shadow:var(--shadow);border:1px solid rgba(15,23,42,.05);margin-bottom:22px}
    .hero-sector h1{font-size:clamp(32px,5vw,52px);font-weight:900;line-height:1.2;letter-spacing:-.5px}
    .hero-sector h1 .grad{background:linear-gradient(120deg,var(--blue),var(--emerald));-webkit-background-clip:text;background-clip:text;color:transparent}
    .hero-sector .lead{font-size:19px;color:var(--muted);margin:20px auto 30px;max-width:640px}
    .hero-cta{display:flex;gap:14px;flex-wrap:wrap;justify-content:center}
    .chips{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-top:30px}
    .chip{background:#fff;border:1px solid rgba(15,23,42,.06);box-shadow:var(--shadow);border-radius:999px;padding:8px 16px;font-weight:700;font-size:14px;display:inline-flex;gap:8px;align-items:center}
    .chip .d{width:8px;height:8px;border-radius:50%;background:var(--emerald)}
    .features{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}
    .feat{background:var(--card);border-radius:20px;padding:28px;box-shadow:var(--shadow);border:1px solid rgba(15,23,42,.05);transition:.25s}
    .feat:hover{transform:translateY(-6px)}
    .feat .fi{width:54px;height:54px;border-radius:15px;display:grid;place-items:center;color:#fff;margin-bottom:18px;font-size:26px}
    .feat h3{font-size:18px;font-weight:800;margin-bottom:8px}
    .feat p{color:var(--muted);font-size:15px}
    .bg-blue{background:var(--blue)} .bg-emerald{background:var(--emerald)}
    .bg-amber{background:var(--amber)} .bg-violet{background:var(--violet)} .bg-rose{background:var(--rose)}
    @media(max-width:860px){.features{grid-template-columns:1fr}}
    .pricing{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;align-items:stretch}
    @media(max-width:860px){.pricing{grid-template-columns:1fr}}
    .plan{background:var(--card);border-radius:22px;padding:30px 26px;box-shadow:var(--shadow);border:1px solid rgba(15,23,42,.06);display:flex;flex-direction:column;position:relative}
    .plan.pop{border:2px solid var(--blue);box-shadow:0 28px 56px -22px rgba(37,99,235,.45)}
    .plan .tag{position:absolute;top:-13px;inset-inline:0;margin-inline:auto;width:max-content;background:var(--blue);color:#fff;font-size:12px;font-weight:800;padding:5px 16px;border-radius:999px}
    .plan h3{font-size:20px;font-weight:900}
    .plan .ptip{color:var(--muted);font-size:13.5px;min-height:38px}
    .plan .price{font-size:42px;font-weight:900;margin:10px 0 2px;line-height:1}
    .plan .price small{font-size:15px;font-weight:700;color:var(--muted)}
    .plan .per{color:var(--muted);font-size:13px;margin-bottom:20px}
    .plan ul{list-style:none;display:flex;flex-direction:column;gap:11px;margin:4px 0 24px;flex:1}
    .plan li{display:flex;gap:10px;align-items:flex-start;font-size:14.5px;font-weight:600;color:#1e293b}
    .plan li .ck{color:var(--emerald);font-weight:900;flex-shrink:0}
    .plan .btn{justify-content:center;width:100%}
    .plan.pop .btn-ghost{background:var(--blue);color:#fff}
    .price-note{text-align:center;color:var(--muted);font-size:13.5px;margin-top:22px}
    .cmp-wrap{overflow-x:auto;background:#fff;border-radius:20px;box-shadow:var(--shadow);border:1px solid rgba(15,23,42,.05)}
    .cmp{width:100%;border-collapse:collapse;min-width:640px}
    .cmp th,.cmp td{padding:15px 18px;text-align:center;border-bottom:1px solid rgba(15,23,42,.06)}
    .cmp th:first-child,.cmp td:first-child{text-align:start;font-weight:700;color:#334155}
    .cmp thead th{font-size:16px;font-weight:900}
    .cmp thead th .mo{display:block;font-size:12px;font-weight:700;color:var(--muted)}
    .cmp thead th.pop{color:var(--blue)}
    .cmp tbody tr:last-child td{border-bottom:0}
    .cmp tbody tr:nth-child(even){background:#f8fafc}
    .cmp .yes{color:var(--emerald);font-weight:900;font-size:18px}
    .cmp .no{color:#cbd5e1;font-weight:900}
    .faq{max-width:800px;margin:0 auto;display:flex;flex-direction:column;gap:12px}
    .faq details{background:#fff;border-radius:16px;box-shadow:var(--shadow);border:1px solid rgba(15,23,42,.05);padding:2px 22px}
    .faq summary{cursor:pointer;list-style:none;padding:18px 0;font-weight:800;font-size:16px;display:flex;align-items:center;justify-content:space-between;gap:12px}
    .faq summary::-webkit-details-marker{display:none}
    .faq summary::after{content:"+";font-size:26px;color:var(--blue);font-weight:800;transition:.2s;line-height:1}
    .faq details[open] summary::after{transform:rotate(45deg)}
    .faq p{color:var(--muted);padding:0 0 20px;font-size:15px}
    .cta{background:linear-gradient(135deg,var(--blue),var(--blue-dark));border-radius:28px;padding:56px;text-align:center;color:#fff}
    .cta h2{font-size:clamp(26px,4vw,38px);font-weight:900}
    .cta p{color:rgba(255,255,255,.85);font-size:18px;margin:16px 0 30px}
    .cta .btn-primary{background:#fff;color:var(--blue)}
    .cta .btn-primary:hover{background:#eff6ff}
    footer{padding:48px 0 32px;color:var(--muted);text-align:center}
    footer .brand{justify-content:center;margin-bottom:14px;color:var(--ink)}
  </style>
</head>
<body>
  <header>
    <div class="wrap">
      <nav>
        <a href="/" class="brand">
          <div class="logo"><svg class="svg" viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V8l7-5 7 5v13"/><path d="M9 21v-6h6v6"/></svg></div>
          ركائز
        </a>
        <div class="nav-links">
          <a href="/#sectors">القطاعات</a>
          <a href="#pricing">الباقات</a>
          <a href="#faq">الأسئلة</a>
          <a href="mailto:hello@rakeez.app">تواصل</a>
        </div>
        <a href="/app/login" class="btn btn-ghost">تسجيل الدخول</a>
        <a href="/app/register" class="btn btn-primary">ابدأ مجاناً</a>
      </nav>
    </div>
  </header>

  <section class="hero-sector">
    <div class="wrap">
      <a href="/#sectors" class="back">→ كل القطاعات</a>
      <div style="margin-top:20px"><div class="sector-badge">${s.emoji}</div></div>
      <h1>${esc(s.h1a)} <span class="grad">${esc(s.h1b)}</span></h1>
      <p class="lead">${esc(s.lead)}</p>
      <div class="hero-cta">
        <a href="/app/register" class="btn btn-primary">ابدأ تجربتك المجانية</a>
        <a href="#pricing" class="btn btn-ghost">شوف الباقات ←</a>
      </div>
      <div class="chips">
${chips}
      </div>
    </div>
  </section>

  <section id="why" style="background:#fff">
    <div class="wrap">
      <div class="sec-head">
        <span class="pill">لماذا ركائز</span>
        <h2>${esc(s.whyTitle)}</h2>
        <p>${esc(s.whySub)}</p>
      </div>
      <div class="features">
${feats}
      </div>
    </div>
  </section>

  <section id="pricing">
    <div class="wrap">
      <div class="sec-head">
        <span class="pill">الباقات</span>
        <h2>باقات تناسب حجم عملك</h2>
        <p>ابدأ صغيراً وكبِّر وقت ما تحب. ترقية أو تخفيض الباقة في أي وقت.</p>
      </div>
      <div class="pricing">
        <div class="plan">
          <h3>الأساسية</h3>
          <p class="ptip">للبداية والأعمال الصغيرة بوحدة واحدة.</p>
          <div class="price">15 <small>ريال</small></div>
          <div class="per">شهرياً</div>
          <ul>
${planList(basic)}
          </ul>
          <a href="/app/register" class="btn btn-ghost" style="border:1.5px solid var(--blue);color:var(--blue)">ابدأ بالأساسية</a>
        </div>
        <div class="plan pop">
          <span class="tag">الأكثر طلباً</span>
          <h3>الاحترافية</h3>
          <p class="ptip">للأعمال النامية ومتعددة الفروع.</p>
          <div class="price">35 <small>ريال</small></div>
          <div class="per">شهرياً</div>
          <ul>
${planList(pro)}
          </ul>
          <a href="/app/register" class="btn btn-ghost">ابدأ بالاحترافية</a>
        </div>
        <div class="plan">
          <h3>المؤسسات</h3>
          <p class="ptip">للسلاسل والمؤسسات الكبيرة.</p>
          <div class="price">80 <small>ريال</small></div>
          <div class="per">شهرياً</div>
          <ul>
${planList(ent)}
          </ul>
          <a href="/app/register" class="btn btn-ghost" style="border:1.5px solid var(--blue);color:var(--blue)">ابدأ بالمؤسسات</a>
        </div>
      </div>
      <p class="price-note">الأسعار مبدئية وقابلة للتعديل · تجربة مجانية قبل الاشتراك · يمكن تغيير الباقة في أي وقت.</p>
    </div>
  </section>

  <section style="background:#fff;padding-top:0">
    <div class="wrap">
      <div class="sec-head">
        <span class="pill">مقارنة الباقات</span>
        <h2>قارن واختر الأنسب</h2>
      </div>
      <div class="cmp-wrap">
        <table class="cmp">
          <thead>
            <tr>
              <th>الميزة</th>
              <th>الأساسية<span class="mo">15 ريال/شهر</span></th>
              <th class="pop">الاحترافية<span class="mo">35 ريال/شهر</span></th>
              <th>المؤسسات<span class="mo">80 ريال/شهر</span></th>
            </tr>
          </thead>
          <tbody>
${cmpRows(s)}
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section id="faq">
    <div class="wrap">
      <div class="sec-head">
        <span class="pill">أسئلة شائعة</span>
        <h2>أسئلة عن النظام</h2>
      </div>
      <div class="faq">
${faqHtml}
      </div>
    </div>
  </section>

  <section>
    <div class="wrap">
      <div class="cta">
        <h2>جاهز تبدأ؟</h2>
        <p>سجّل الآن وفعّل نظامك خلال دقائق — تجربة مجانية بدون بطاقة.</p>
        <a href="/app/register" class="btn btn-primary">ابدأ تجربتك المجانية</a>
      </div>
    </div>
  </section>

  <footer>
    <div class="wrap">
      <a href="/" class="brand"><div class="logo"><svg class="svg" viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V8l7-5 7 5v13"/><path d="M9 21v-6h6v6"/></svg></div>ركائز</a>
      <p>منصّة الأعمال المتكاملة © ۲۰۲۶ — جميع الحقوق محفوظة.</p>
    </div>
  </footer>
</body>
</html>
`
}

// ---- Sector data (order = homepage grid order) ----
const SECTORS = [
  {
    file: 'retail', emoji: '🛒', card: 'المتاجر ونقاط البيع', h1a: 'المتاجر', h1b: 'ونقاط البيع',
    lead: 'نظام متكامل لإدارة متجرك من مكان واحد: كاشير سريع، مخزون دقيق، فواتير ضريبية، وتقارير لحظية لأرباحك ومبيعاتك.',
    chips: ['نقاط البيع', 'المخزون والأصناف', 'الفواتير الضريبية', 'المحاسبة', 'الموارد البشرية', 'المتجر الإلكتروني'],
    whyTitle: 'كل ما يحتاجه متجرك في نظام واحد', whySub: 'بدل برامج منفصلة للكاشير والمخزون والمحاسبة — ركائز يجمعها كلها بقاعدة بيانات موحّدة.',
    features: [
      { ic: '🖥️', h: 'كاشير سريع', p: 'بِع واطبع الفاتورة خلال ثوانٍ، بواجهة بسيطة تناسب أي موظف وأي شاشة لمس.' },
      { ic: '📦', h: 'مخزون دقيق', p: 'تتبّع الكميات والتكلفة لحظياً، وتنبيهات عند وصول الصنف إلى الحد الأدنى.' },
      { ic: '🧾', h: 'فواتير ضريبية (ZATCA)', p: 'فواتير إلكترونية متوافقة مع المرحلة الأولى برمز QR ضريبي جاهز للطباعة.' },
      { ic: '📶', h: 'يعمل دون إنترنت', p: 'الكاشير يكمل البيع وقت انقطاع الشبكة ويزامن تلقائياً فور عودة الاتصال.' },
      { ic: '🏬', h: 'عدّة فروع ومستودعات', p: 'أدِر أكثر من فرع ومستودع من حساب واحد، مع نقل مخزون وتقارير لكل فرع.' },
      { ic: '📊', h: 'تقارير لحظية', p: 'مبيعات اليوم، الأصناف الأكثر طلباً، الأرباح، وأداء كل موظف بضغطة واحدة.' },
    ],
    core: ['نقاط البيع (الكاشير)', 'المخزون والأصناف'], pro: ['الكاشير دون إنترنت', 'المتجر الإلكتروني'],
    faq: [{ q: 'هل يعمل الكاشير بدون إنترنت؟', a: 'نعم، في الباقة الاحترافية فأعلى. يكمل الكاشير البيع وقت انقطاع الشبكة ويزامن العمليات تلقائياً عند عودة الاتصال.' }],
  },
  {
    file: 'car_showroom', emoji: '🚗', card: 'معارض السيارات', h1a: 'معارض', h1b: 'السيارات',
    lead: 'أدِر معرض سياراتك باحتراف: سجل المركبات ومواصفاتها، عروض الأسعار والبيع، ومتابعة العملاء المحتملين حتى إتمام الصفقة.',
    chips: ['سجل المركبات', 'عروض الأسعار', 'إدارة العملاء', 'المحاسبة', 'الأقساط والتمويل', 'الموارد البشرية'],
    whyTitle: 'من عرض السيارة حتى إتمام البيع', whySub: 'نظام واحد يربط المخزون والعملاء والمبيعات والمحاسبة لمعرضك.',
    features: [
      { ic: '🚙', h: 'سجل المركبات', p: 'بطاقة لكل سيارة بالمواصفات والصور والتكلفة وحالة التوفّر.' },
      { ic: '📝', h: 'عروض وبيع', p: 'أصدر عرض سعر احترافي وحوّله لفاتورة بيع بضغطة.' },
      { ic: '🤝', h: 'متابعة العملاء المحتملين', p: 'سجّل اهتمام كل عميل وتابع المفاوضات حتى الإغلاق.' },
      { ic: '💳', h: 'التمويل والأقساط', p: 'نظّم خطط التقسيط وتتبّع الدفعات المستحقة تلقائياً.' },
      { ic: '🧾', h: 'فواتير ضريبية (ZATCA)', p: 'فواتير إلكترونية متوافقة برمز QR جاهزة للطباعة.' },
      { ic: '📊', h: 'تقارير المبيعات', p: 'أكثر الموديلات مبيعاً، هوامش الربح، وأداء المندوبين.' },
    ],
    core: ['سجل المركبات والمواصفات', 'عروض الأسعار والبيع'], pro: ['إدارة العملاء المحتملين (CRM)', 'التمويل والأقساط'],
    faq: [{ q: 'هل أقدر أتابع العملاء المحتملين حتى البيع؟', a: 'نعم، تسجّل كل عميل واهتمامه وتتابع مراحل المفاوضة حتى إتمام الصفقة في الباقة الاحترافية فأعلى.' }],
  },
  {
    file: 'real_estate', emoji: '🏢', card: 'العقارات والأملاك', h1a: 'العقارات', h1b: 'والأملاك',
    lead: 'إدارة متكاملة لعقاراتك وأملاكك: سجل الوحدات، عقود الإيجار، تحصيل الإيجارات وتذكيرها، وكشوف لكل مالك.',
    chips: ['سجل الوحدات', 'عقود الإيجار', 'تحصيل الإيجارات', 'المحاسبة', 'بوابة الملّاك', 'الصيانة'],
    whyTitle: 'كل أملاكك وعقودك في مكان واحد', whySub: 'من تأجير الوحدة حتى تحصيل الإيجار وكشف المالك — بدون جداول متفرّقة.',
    features: [
      { ic: '🏘️', h: 'سجل الوحدات والعقارات', p: 'بطاقة لكل وحدة بحالتها وإيجارها ومستأجرها الحالي.' },
      { ic: '📄', h: 'عقود الإيجار', p: 'أنشئ عقوداً بمددها ودفعاتها وتجديداتها بشكل منظّم.' },
      { ic: '💰', h: 'تحصيل وتذكير', p: 'جدول الإيجارات المستحقة وتذكير المستأجرين تلقائياً.' },
      { ic: '👤', h: 'بوابة الملّاك والمستأجرين', p: 'كشف حساب ومستندات لكل طرف في مكان آمن.' },
      { ic: '🛠️', h: 'طلبات الصيانة', p: 'استقبل بلاغات الصيانة وتابع تنفيذها وتكاليفها.' },
      { ic: '📊', h: 'تقارير الإشغال والدخل', p: 'نِسب الإشغال، الدخل الشهري، والمتأخرات لكل عقار.' },
    ],
    core: ['سجل الوحدات والعقارات', 'عقود الإيجار'], pro: ['تحصيل الإيجارات وتذكيرها', 'بوابة الملّاك/المستأجرين'],
    faq: [{ q: 'هل يذكّر النظام بمواعيد الإيجارات؟', a: 'نعم، يجدول الإيجارات المستحقة ويذكّر المستأجرين تلقائياً، مع متابعة المتأخرات في الباقة الاحترافية فأعلى.' }],
  },
  {
    file: 'car_rental', emoji: '🔑', card: 'تأجير السيارات', h1a: 'تأجير', h1b: 'السيارات',
    lead: 'نظّم أسطول سياراتك وعقود التأجير: توفّر المركبات، التسعير حسب المدة، التسليم والاستلام، وجدولة الصيانة.',
    chips: ['أسطول المركبات', 'عقود التأجير', 'التسعير بالمدة', 'المحاسبة', 'الصيانة', 'المخالفات'],
    whyTitle: 'أسطولك وعقودك تحت السيطرة', whySub: 'من حجز السيارة حتى إرجاعها وفوترتها — بمتابعة دقيقة للتوفّر والصيانة.',
    features: [
      { ic: '🚘', h: 'أسطول وتوفّر', p: 'حالة كل مركبة لحظياً: متاحة، مؤجّرة، أو في الصيانة.' },
      { ic: '📄', h: 'عقود التأجير', p: 'عقد يومي/أسبوعي بمدة وسعر ووديعة وتسليم منظّم.' },
      { ic: '⏱️', h: 'التسعير حسب المدة', p: 'احتساب تلقائي للأجرة والتمديد والتأخير والوقود.' },
      { ic: '🧾', h: 'فواتير ضريبية (ZATCA)', p: 'فوترة إلكترونية متوافقة برمز QR عند الإرجاع.' },
      { ic: '🛠️', h: 'جدولة الصيانة', p: 'تذكير الصيانة الدورية وتتبّع تكلفة كل مركبة.' },
      { ic: '📊', h: 'تقارير الأسطول', p: 'نسبة التأجير، دخل كل سيارة، والمركبات الأكثر طلباً.' },
    ],
    core: ['أسطول المركبات والتوفّر', 'عقود التأجير'], pro: ['التسعير حسب المدة', 'جدولة الصيانة والتسليم'],
    faq: [{ q: 'هل يحسب النظام الأجرة والتأخير تلقائياً؟', a: 'نعم، يحتسب الأجرة حسب المدة ويضيف التمديد والتأخير والوقود تلقائياً عند الإرجاع.' }],
  },
  {
    file: 'auto_garage', emoji: '🔧', card: 'ورش الصيانة', h1a: 'ورش', h1b: 'الصيانة',
    lead: 'أدِر ورشتك من استقبال المركبة حتى التسليم: أوامر تشغيل، قطع غيار، أجور عمالة، وتاريخ صيانة لكل مركبة.',
    chips: ['أوامر التشغيل', 'قطع الغيار', 'أجور العمالة', 'المحاسبة', 'تاريخ المركبة', 'الفوترة'],
    whyTitle: 'ورشتك منظّمة من الاستقبال للتسليم', whySub: 'اربط أوامر الإصلاح بالقطع والعمالة والفوترة في نظام واحد.',
    features: [
      { ic: '🧰', h: 'أوامر التشغيل', p: 'أمر إصلاح لكل مركبة بالأعطال والإجراءات والحالة.' },
      { ic: '⚙️', h: 'قطع الغيار والمخزون', p: 'اربط القطع المستهلكة بأمر العمل واخصمها من المخزون.' },
      { ic: '👷', h: 'أجور العمالة', p: 'احتسب ساعات الفنيين وأجورهم ضمن تكلفة الإصلاح.' },
      { ic: '🚗', h: 'بطاقة تاريخ المركبة', p: 'سجل كامل لكل زيارة وصيانة لكل مركبة وعميل.' },
      { ic: '🔔', h: 'تذكير الصيانة الدورية', p: 'ذكّر العميل بموعد الصيانة القادمة تلقائياً.' },
      { ic: '🧾', h: 'فواتير ضريبية (ZATCA)', p: 'فوترة إلكترونية متوافقة برمز QR عند التسليم.' },
    ],
    core: ['أوامر التشغيل والإصلاح', 'قطع الغيار والمخزون'], pro: ['تذكير الصيانة الدورية', 'بطاقة تاريخ المركبة'],
    faq: [{ q: 'هل أقدر أربط قطع الغيار بأمر الإصلاح؟', a: 'نعم، تُخصم القطع المستخدمة من المخزون وتُضاف لتكلفة أمر العمل تلقائياً مع أجور العمالة.' }],
  },
  {
    file: 'laundry', emoji: '🧺', card: 'مغاسل الملابس', h1a: 'مغاسل', h1b: 'الملابس',
    lead: 'أدِر مغسلتك بسهولة: استقبال الطلبات وتتبّعها، التسعير حسب القطعة والخدمة، وإشعار العميل عند الجاهزية.',
    chips: ['طلبات الغسيل', 'تتبّع الحالة', 'التسعير بالخدمة', 'المحاسبة', 'إشعار الجاهزية', 'الولاء'],
    whyTitle: 'كل طلب غسيل متتبّع حتى التسليم', whySub: 'من استلام الملابس حتى تسليمها للعميل — بإيصال واضح وتسعير مرن.',
    features: [
      { ic: '🧾', h: 'طلبات وإيصالات', p: 'سجّل الطلب بالقطع والخدمات واطبع إيصالاً برقم تتبّع.' },
      { ic: '🔄', h: 'تتبّع الحالة', p: 'تابع كل طلب: قيد الغسيل، جاهز، أو مُسلَّم.' },
      { ic: '🏷️', h: 'تسعير حسب القطعة', p: 'أسعار مرنة لكل نوع قطعة وخدمة (كي، تنظيف جاف…).' },
      { ic: '🔔', h: 'إشعار الجاهزية', p: 'أبلغ العميل تلقائياً عند جاهزية طلبه للاستلام.' },
      { ic: '⭐', h: 'اشتراكات وولاء', p: 'باقات واشتراكات ونقاط ولاء لعملائك الدائمين.' },
      { ic: '📊', h: 'تقارير يومية', p: 'عدد الطلبات، الإيرادات، والخدمات الأكثر طلباً.' },
    ],
    core: ['طلبات الغسيل وتتبّعها', 'التسعير حسب القطعة/الخدمة'], pro: ['إشعار الجاهزية للعميل', 'اشتراكات ونقاط ولاء'],
    faq: [{ q: 'هل يُشعر النظام العميل عند جاهزية طلبه؟', a: 'نعم، في الباقة الاحترافية فأعلى يُرسل إشعار جاهزية تلقائياً مع رقم تتبّع للطلب.' }],
  },
  {
    file: 'school', emoji: '🎓', card: 'المدارس ورياض الأطفال', h1a: 'المدارس', h1b: 'ورياض الأطفال',
    lead: 'نظام متكامل لمدرستك أو روضتك: سجل الطلاب والصفوف، الرسوم والأقساط، الحضور، وبوابة لأولياء الأمور.',
    chips: ['سجل الطلاب', 'الرسوم والأقساط', 'الحضور', 'المحاسبة', 'بوابة أولياء الأمور', 'التقارير'],
    whyTitle: 'إدارة مدرستك بالكامل من مكان واحد', whySub: 'من تسجيل الطالب حتى تحصيل الرسوم ومتابعة الحضور والتواصل مع الأهل.',
    features: [
      { ic: '👩‍🎓', h: 'سجل الطلاب والصفوف', p: 'ملف لكل طالب بصفّه وبياناته ومستنداته.' },
      { ic: '💰', h: 'الرسوم والأقساط', p: 'جدول رسوم وأقساط مع تذكير أولياء الأمور بالمستحق.' },
      { ic: '🗓️', h: 'الحضور والغياب', p: 'سجّل الحضور اليومي وأصدر تقارير للأهل والإدارة.' },
      { ic: '👪', h: 'بوابة أولياء الأمور', p: 'يتابع الأهل الرسوم والحضور والإشعارات بأمان.' },
      { ic: '🧾', h: 'فواتير وإيصالات', p: 'إيصالات رسوم رسمية وفواتير ضريبية عند الحاجة.' },
      { ic: '📊', h: 'تقارير الإدارة', p: 'التحصيل، المتأخرات، وأعداد الطلاب لكل صف.' },
    ],
    core: ['سجل الطلاب والصفوف', 'الرسوم والأقساط'], pro: ['بوابة أولياء الأمور', 'الحضور والتقارير'],
    faq: [{ q: 'هل يوجد بوابة لأولياء الأمور؟', a: 'نعم، في الباقة الاحترافية فأعلى يتابع ولي الأمر الرسوم والحضور والإشعارات من بوابة آمنة.' }],
  },
  {
    file: 'law_firm', emoji: '⚖️', card: 'مكاتب المحاماة', h1a: 'مكاتب', h1b: 'المحاماة',
    lead: 'أدِر مكتبك القانوني باحتراف: القضايا والملفات، المواعيد والجلسات، ساعات العمل والفوترة، وبوابة للموكّلين.',
    chips: ['القضايا والملفات', 'المواعيد والجلسات', 'ساعات العمل', 'المحاسبة', 'بوابة الموكّل', 'الفوترة'],
    whyTitle: 'قضاياك ومواعيدك وفواتيرك منظّمة', whySub: 'من فتح الملف حتى الفوترة بالساعة — بدون تشتّت بين الملفات الورقية.',
    features: [
      { ic: '📁', h: 'القضايا والملفات', p: 'ملف لكل قضية بمستنداتها وأطرافها وحالتها.' },
      { ic: '🗓️', h: 'المواعيد والجلسات', p: 'تقويم بالجلسات والمواعيد مع تنبيهات مسبقة.' },
      { ic: '⏱️', h: 'ساعات العمل', p: 'سجّل الساعات على كل قضية ووكيل لفوترتها بدقّة.' },
      { ic: '🧾', h: 'الفوترة', p: 'فواتير أتعاب بالساعة أو بالاتفاق مع كشف مفصّل.' },
      { ic: '👤', h: 'بوابة الموكّل', p: 'يطّلع الموكّل على مستجدات قضيته ومستنداته بأمان.' },
      { ic: '📊', h: 'تقارير المكتب', p: 'القضايا النشطة، الإيرادات، وإنتاجية كل محامٍ.' },
    ],
    core: ['القضايا والملفات', 'المواعيد والجلسات'], pro: ['ساعات العمل والفوترة', 'بوابة الموكّل'],
    faq: [{ q: 'هل أقدر أفوتر بالساعة؟', a: 'نعم، تسجّل ساعات العمل على كل قضية وتصدر فاتورة أتعاب مفصّلة بالساعة أو بالاتفاق في الباقة الاحترافية فأعلى.' }],
  },
  {
    file: 'hotel', emoji: '🏨', card: 'الفنادق والحجوزات', h1a: 'الفنادق', h1b: 'والحجوزات',
    lead: 'أدِر فندقك من الحجز حتى المغادرة: إدارة الغرف والتوفّر، تسجيل الدخول والخروج، الحجز أونلاين، والفوترة.',
    chips: ['إدارة الغرف', 'الحجوزات', 'الدخول/الخروج', 'المحاسبة', 'الحجز أونلاين', 'التقارير'],
    whyTitle: 'فندقك مُدار من الحجز حتى المغادرة', whySub: 'توفّر الغرف والحجوزات والفوترة في نظام واحد سهل.',
    features: [
      { ic: '🛏️', h: 'إدارة الغرف والتوفّر', p: 'حالة كل غرفة لحظياً: متاحة، محجوزة، أو تحت التنظيف.' },
      { ic: '📅', h: 'الحجوزات', p: 'تقويم حجوزات واضح مع أسعار الموسم والباقات.' },
      { ic: '🔑', h: 'تسجيل الدخول والخروج', p: 'عمليات دخول وخروج سريعة مع حساب الإقامة.' },
      { ic: '🌐', h: 'الحجز أونلاين', p: 'استقبل حجوزات مباشرة من عملائك عبر الإنترنت.' },
      { ic: '🧾', h: 'فواتير ضريبية (ZATCA)', p: 'فوترة إلكترونية متوافقة برمز QR عند المغادرة.' },
      { ic: '📊', h: 'تقارير الإشغال', p: 'نسبة الإشغال، الدخل لكل غرفة، ومتوسط الإقامة.' },
    ],
    core: ['إدارة الغرف والحجوزات', 'تسجيل الدخول/الخروج'], pro: ['الحجز أونلاين', 'الربط مع قنوات الحجز'],
    faq: [{ q: 'هل يدعم الحجز أونلاين؟', a: 'نعم، في الباقة الاحترافية فأعلى تستقبل حجوزات مباشرة من عملائك عبر الإنترنت مع تحديث التوفّر تلقائياً.' }],
  },
  {
    file: 'chalets', emoji: '🏝️', card: 'الشاليهات والاستراحات', h1a: 'الشاليهات', h1b: 'والاستراحات',
    lead: 'أدِر شاليهاتك واستراحاتك بسهولة: تقويم التوفّر والحجز، العقود والدفعات، الحجز أونلاين، والعربون.',
    chips: ['تقويم الحجز', 'العقود والدفعات', 'العربون', 'المحاسبة', 'الحجز أونلاين', 'التقارير'],
    whyTitle: 'حجوزات شاليهاتك بلا تعارض', whySub: 'تقويم واضح، عربون، ودفعات — كل شيء منظّم ومتاح أونلاين.',
    features: [
      { ic: '📅', h: 'تقويم التوفّر', p: 'شاهد المتاح والمحجوز لكل وحدة ومنع التعارض.' },
      { ic: '📄', h: 'العقود والدفعات', p: 'عقد لكل حجز بمدته وسعره ودفعاته المنظّمة.' },
      { ic: '💵', h: 'العربون والإلغاء', p: 'سجّل العربون وسياسة الإلغاء واحتسبها تلقائياً.' },
      { ic: '🌐', h: 'الحجز أونلاين', p: 'استقبل طلبات الحجز من العملاء عبر الإنترنت.' },
      { ic: '🧾', h: 'فواتير وإيصالات', p: 'إيصالات ودفعات واضحة لكل حجز.' },
      { ic: '📊', h: 'تقارير الدخل', p: 'الإشغال والدخل لكل وحدة وأكثر الفترات طلباً.' },
    ],
    core: ['تقويم التوفّر والحجز', 'العقود والدفعات'], pro: ['الحجز أونلاين', 'العربون وسياسة الإلغاء'],
    faq: [{ q: 'هل يمنع النظام الحجز المزدوج؟', a: 'نعم، التقويم يحجز الوحدة فور تأكيدها ويمنع أي تعارض في المواعيد لنفس الوحدة.' }],
  },
  {
    file: 'tailoring', emoji: '✂️', card: 'الخياطة والتفصيل', h1a: 'الخياطة', h1b: 'والتفصيل',
    lead: 'أدِر محل التفصيل: طلبات العملاء ومقاساتهم، مواعيد التسليم، مخزون الأقمشة، وإشعار الجاهزية.',
    chips: ['طلبات التفصيل', 'المقاسات', 'مواعيد التسليم', 'مخزون الأقمشة', 'إشعار الجاهزية', 'الفوترة'],
    whyTitle: 'كل طلب تفصيل بمقاساته ومواعيده', whySub: 'من أخذ المقاس حتى التسليم — بدون أوراق ضائعة أو مواعيد منسيّة.',
    features: [
      { ic: '📐', h: 'المقاسات والطلبات', p: 'احفظ مقاسات كل عميل واربطها بطلباته بسهولة.' },
      { ic: '🗓️', h: 'مواعيد التسليم', p: 'حدّد موعد التسليم لكل طلب مع تنبيهات مسبقة.' },
      { ic: '🧵', h: 'مخزون الأقمشة', p: 'تتبّع الأقمشة والإكسسوارات وخصمها عند الاستخدام.' },
      { ic: '🔔', h: 'إشعار الجاهزية', p: 'أبلغ العميل تلقائياً عند جاهزية طلبه.' },
      { ic: '🧾', h: 'فواتير وإيصالات', p: 'إيصال بالعربون والمتبقّي لكل طلب.' },
      { ic: '📊', h: 'تقارير المحل', p: 'الطلبات قيد التنفيذ، الإيرادات، وأكثر الخدمات طلباً.' },
    ],
    core: ['طلبات التفصيل والمقاسات', 'مواعيد التسليم'], pro: ['إشعار الجاهزية', 'مخزون الأقمشة'],
    faq: [{ q: 'هل يحفظ النظام مقاسات العميل؟', a: 'نعم، تُحفظ مقاسات كل عميل وتُستخدم مباشرة في طلباته القادمة بدون إعادة إدخال.' }],
  },
  {
    file: 'travel', emoji: '🕋', card: 'الحج والعمرة والسفر', h1a: 'الحج والعمرة', h1b: 'والسفر',
    lead: 'أدِر مكتب الحج والعمرة والسفر: الباقات والرحلات، حجوزات المعتمرين والحجّاج، إدارة المجموعات، والدفعات.',
    chips: ['الباقات والرحلات', 'حجوزات المعتمرين', 'إدارة المجموعات', 'المحاسبة', 'العقود والدفعات', 'التقارير'],
    whyTitle: 'باقاتك وحجوزاتك ومجموعاتك منظّمة', whySub: 'من بيع الباقة حتى تسيير المجموعة — بمتابعة دقيقة للدفعات والمستندات.',
    features: [
      { ic: '🧳', h: 'الباقات والرحلات', p: 'صمّم باقات بأسعارها وخدماتها ومواعيد رحلاتها.' },
      { ic: '🧑‍🤝‍🧑', h: 'حجوزات المعتمرين', p: 'سجّل كل معتمر/حاج بمستنداته وباقته ودفعاته.' },
      { ic: '👥', h: 'إدارة المجموعات', p: 'نظّم المجموعات والمرافقين وجداول التحرّك.' },
      { ic: '💵', h: 'العقود والدفعات', p: 'عقد ودفعات لكل حجز مع متابعة المستحقات.' },
      { ic: '🧾', h: 'فواتير وإيصالات', p: 'إيصالات ودفعات واضحة لكل عميل.' },
      { ic: '📊', h: 'تقارير الموسم', p: 'المبيعات، أعداد المعتمرين، وأداء الباقات.' },
    ],
    core: ['الباقات والرحلات', 'حجوزات المعتمرين/الحجّاج'], pro: ['إدارة المجموعات', 'العقود والدفعات'],
    faq: [{ q: 'هل أقدر أدير المجموعات والدفعات؟', a: 'نعم، تنظّم المجموعات والمرافقين وتتابع دفعات كل معتمر حتى اكتمالها في الباقة الاحترافية فأعلى.' }],
  },
  {
    file: 'halls', emoji: '🎉', card: 'القاعات والمناسبات', h1a: 'القاعات', h1b: 'والمناسبات',
    lead: 'أدِر قاعاتك ومناسباتك: تقويم الحجوزات، الباقات والخدمات، الحجز أونلاين، والعربون وجدول الدفعات.',
    chips: ['تقويم المناسبات', 'الباقات والخدمات', 'العربون', 'المحاسبة', 'الحجز أونلاين', 'التقارير'],
    whyTitle: 'مناسباتك محجوزة ومنظّمة', whySub: 'تقويم واضح بلا تعارض، باقات خدمات، ودفعات متابَعة حتى يوم المناسبة.',
    features: [
      { ic: '📅', h: 'تقويم الحجوزات', p: 'شاهد المتاح والمحجوز لكل قاعة ومنع التعارض.' },
      { ic: '🎀', h: 'الباقات والخدمات', p: 'باقات بأسعارها وخدماتها الإضافية (ضيافة، تنسيق…).' },
      { ic: '💵', h: 'العربون والدفعات', p: 'سجّل العربون وجدول الدفعات حتى يوم المناسبة.' },
      { ic: '🌐', h: 'الحجز أونلاين', p: 'استقبل طلبات الحجز والاستفسار عبر الإنترنت.' },
      { ic: '🧾', h: 'فواتير وإيصالات', p: 'عقود وإيصالات واضحة لكل مناسبة.' },
      { ic: '📊', h: 'تقارير الدخل', p: 'الإشغال والدخل لكل قاعة وأكثر المواسم طلباً.' },
    ],
    core: ['تقويم المناسبات والحجز', 'الباقات والخدمات'], pro: ['الحجز أونلاين', 'العربون وجدول الدفعات'],
    faq: [{ q: 'هل يمنع النظام تعارض الحجوزات؟', a: 'نعم، يحجز القاعة فور التأكيد ويمنع أي حجز مزدوج في نفس التاريخ.' }],
  },
  {
    file: 'farm', emoji: '🐣', card: 'المزارع والمواشي', h1a: 'المزارع', h1b: 'والمواشي',
    lead: 'أدِر مزرعتك ومواشيك: سجل القطيع أو المحاصيل، المخزون والأعلاف، تتبّع الإنتاج، ودورة التكاليف.',
    chips: ['سجل القطيع', 'المخزون والأعلاف', 'الإنتاج', 'المحاسبة', 'التكاليف', 'التقارير'],
    whyTitle: 'مزرعتك وإنتاجها تحت السيطرة', whySub: 'من الأعلاف والتكاليف حتى الإنتاج والمبيعات — في نظام واحد.',
    features: [
      { ic: '🐄', h: 'سجل القطيع/المحاصيل', p: 'بطاقة لكل مجموعة بحالتها وأعدادها ومواعيدها.' },
      { ic: '🌾', h: 'المخزون والأعلاف', p: 'تتبّع الأعلاف والمستلزمات وخصمها عند الاستهلاك.' },
      { ic: '🥚', h: 'تتبّع الإنتاج', p: 'سجّل الإنتاج اليومي (حليب، بيض، محصول) وقارنه.' },
      { ic: '💰', h: 'دورة التكاليف', p: 'احسب تكلفة الإنتاج مقابل المبيعات والأرباح.' },
      { ic: '🧾', h: 'فواتير المبيعات', p: 'فوترة مبيعاتك بإيصالات واضحة للعملاء.' },
      { ic: '📊', h: 'تقارير المزرعة', p: 'الإنتاج، التكاليف، والهوامش لكل دورة.' },
    ],
    core: ['سجل القطيع/المحاصيل', 'المخزون والأعلاف'], pro: ['تتبّع الإنتاج', 'دورة التكاليف والأرباح'],
    faq: [{ q: 'هل أقدر أتابع الإنتاج والتكاليف؟', a: 'نعم، تسجّل الإنتاج اليومي وتقارنه بالتكاليف لتعرف هامش الربح لكل دورة في الباقة الاحترافية فأعلى.' }],
  },
  {
    file: 'engineering', emoji: '📐', card: 'الاستشارات الهندسية', h1a: 'الاستشارات', h1b: 'الهندسية',
    lead: 'أدِر مكتبك الهندسي: المشاريع ومراحلها، المهام والفريق، ساعات العمل والفوترة، وبوابة للعميل.',
    chips: ['المشاريع والمراحل', 'المهام والفريق', 'ساعات العمل', 'المحاسبة', 'بوابة العميل', 'الفوترة'],
    whyTitle: 'مشاريعك ومهامك وفواتيرك في مكان واحد', whySub: 'من فتح المشروع حتى الفوترة بالمرحلة أو بالساعة — بمتابعة واضحة للفريق.',
    features: [
      { ic: '🏗️', h: 'المشاريع والمراحل', p: 'قسّم كل مشروع لمراحل بمواعيدها ونسب إنجازها.' },
      { ic: '✅', h: 'المهام والفريق', p: 'وزّع المهام على الفريق وتابع تقدّمها لحظياً.' },
      { ic: '⏱️', h: 'ساعات العمل', p: 'سجّل الساعات على كل مشروع لفوترتها بدقّة.' },
      { ic: '🧾', h: 'الفوترة بالمرحلة', p: 'فوتر بالمرحلة أو بالدفعة أو بالساعة مع كشف مفصّل.' },
      { ic: '👤', h: 'بوابة العميل', p: 'يتابع العميل نسب الإنجاز والمستندات بأمان.' },
      { ic: '📊', h: 'تقارير المشاريع', p: 'التقدّم، الساعات، والربحية لكل مشروع.' },
    ],
    core: ['المشاريع والمراحل', 'المهام والفريق'], pro: ['ساعات العمل والفوترة', 'بوابة العميل'],
    faq: [{ q: 'هل أقدر أفوتر حسب مراحل المشروع؟', a: 'نعم، تفوتر بالمرحلة أو بالدفعة أو بالساعة، مع كشف مفصّل للعميل في الباقة الاحترافية فأعلى.' }],
  },
  {
    file: 'sweets', emoji: '🍬', card: 'الحلوى والتصنيع', h1a: 'الحلوى', h1b: 'والتصنيع',
    lead: 'أدِر إنتاج ومبيعات الحلوى: الوصفات وأوامر التصنيع، مخزون المكوّنات، نقاط البيع للمعرض، والطلبات.',
    chips: ['الوصفات والتصنيع', 'مخزون المكوّنات', 'نقاط البيع', 'المحاسبة', 'الطلبات والتوصيل', 'التقارير'],
    whyTitle: 'من المكوّن حتى البيع في نظام واحد', whySub: 'اربط الوصفات والتصنيع بالمخزون والمعرض ونقاط البيع.',
    features: [
      { ic: '📋', h: 'الوصفات وأوامر التصنيع', p: 'عرّف وصفة كل منتج واحسب تكلفته من مكوّناته.' },
      { ic: '🧂', h: 'مخزون المكوّنات', p: 'اخصم المكوّنات تلقائياً عند التصنيع وتابع النواقص.' },
      { ic: '🛒', h: 'نقاط البيع للمعرض', p: 'كاشير سريع لبيع منتجاتك في المعرض.' },
      { ic: '🚚', h: 'الطلبات والتوصيل', p: 'استقبل طلبات العملاء والمناسبات وتابع تسليمها.' },
      { ic: '🧾', h: 'فواتير ضريبية (ZATCA)', p: 'فوترة إلكترونية متوافقة برمز QR جاهزة للطباعة.' },
      { ic: '📊', h: 'تقارير الإنتاج والبيع', p: 'التكاليف، الأكثر مبيعاً، وهوامش الربح.' },
    ],
    core: ['الوصفات وأوامر التصنيع', 'مخزون المكوّنات'], pro: ['نقاط البيع للمعرض', 'الطلبات والتوصيل'],
    faq: [{ q: 'هل يحسب النظام تكلفة المنتج من مكوّناته؟', a: 'نعم، تعرّف وصفة كل منتج فيحسب تكلفته ويخصم المكوّنات من المخزون تلقائياً عند التصنيع.' }],
  },
]

// ---- Write sector pages ----
for (const s of SECTORS) {
  writeFileSync(join(OUT, `${s.file}.html`), page(s))
  console.log('wrote', `public/sectors/${s.file}.html`)
}

// ---- Rewrite the homepage sector grid so every card links to its page ----
const idxPath = join(ROOT, 'public', 'index.html')
let idx = readFileSync(idxPath, 'utf8')
const cards = SECTORS.map((s) =>
  `        <a class="sector" href="/sectors/${s.file}.html"><div class="si">${s.emoji}</div>${s.card}</a>`
).join('\n')
const block = `<div class="sectors">\n${cards}\n      </div>`
idx = idx.replace(/<div class="sectors">[\s\S]*?<\/div>\s*<\/div>\s*<\/section>/, `${block}\n    </div>\n  </section>`)
writeFileSync(idxPath, idx)
console.log('rewrote homepage sector grid:', SECTORS.length, 'cards')
