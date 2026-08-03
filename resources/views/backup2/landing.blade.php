<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پایش‌گر | سامانه هوشمند نظارت بر تعمیرات تاسیسات علمک</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --navy-950:#0B2033;
    --navy-900:#0F2A43;
    --navy-800:#16405C;
    --blueprint:#2B5872;
    --blueprint-soft:#3E6C86;
    --paper:#EEF0EA;
    --paper-dim:#E3E6DD;
    --ink:#132433;
    --muted:#5C7488;
    --amber:#D98F2B;
    --amber-dark:#B8721B;
    --green:#3F7A5C;
    --green-soft:#E4EFE8;
    --radius:10px;
    --maxw:1180px;
  }
  *{box-sizing:border-box;margin:0;padding:0;}
  html{scroll-behavior:smooth;}
  body{
    font-family:'Vazirmatn',sans-serif;
    background:var(--paper);
    color:var(--ink);
    line-height:1.75;
    -webkit-font-smoothing:antialiased;
  }
  .mono{font-family:'JetBrains Mono',monospace;direction:ltr;unicode-bidi:isolate;}
  img,svg{display:block;max-width:100%;}
  a{color:inherit;text-decoration:none;}
  .wrap{max-width:var(--maxw);margin:0 auto;padding:0 28px;}
  h1,h2,h3{font-weight:800;letter-spacing:-0.01em;}
  .eyebrow{
    display:inline-flex;align-items:center;gap:8px;
    font-family:'JetBrains Mono',monospace;
    font-size:12.5px;letter-spacing:.14em;
    text-transform:uppercase;color:var(--amber-dark);
    font-weight:600;
  }
  .eyebrow::before{content:"";width:20px;height:1px;background:var(--amber-dark);}

  /* ===== PROGRESS BAR ===== */
  .scroll-progress{position:fixed;top:0;right:0;height:3px;background:linear-gradient(90deg,var(--amber),#F0C674);width:0%;z-index:100;transition:width .1s linear;box-shadow:0 0 10px rgba(217,143,43,.6);}

  /* ===== HEADER ===== */
  header.site-header{
    position:sticky;top:0;z-index:50;
    background:rgba(11,32,51,.92);
    backdrop-filter:blur(10px);
    border-bottom:1px solid rgba(255,255,255,.08);
  }
  .nav{
    display:flex;align-items:center;justify-content:space-between;
    padding:16px 28px;max-width:var(--maxw);margin:0 auto;
  }
  .brand{display:flex;align-items:center;gap:10px;color:#fff;font-weight:800;font-size:18px;}
  .brand .dot{width:9px;height:9px;border-radius:50%;background:var(--amber);box-shadow:0 0 0 4px rgba(217,143,43,.22);animation:pulseDot 2.4s ease-in-out infinite;}
  @keyframes pulseDot{0%,100%{box-shadow:0 0 0 4px rgba(217,143,43,.22);}50%{box-shadow:0 0 0 8px rgba(217,143,43,.1);}}
  .nav-links{display:flex;gap:30px;align-items:center;}
  .nav-links a{position:relative;color:#C7D2DB;font-size:14.5px;transition:color .2s;padding-bottom:4px;}
  .nav-links a::after{content:"";position:absolute;bottom:0;right:0;width:0;height:1px;background:var(--amber);transition:width .25s ease;}
  .nav-links a:hover{color:#fff;}
  .nav-links a:hover::after{width:100%;}
  .btn{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    padding:11px 22px;border-radius:7px;font-weight:700;font-size:14.5px;
    cursor:pointer;border:1px solid transparent;transition:all .25s cubic-bezier(.2,.8,.2,1);
  }
  .btn-amber{background:linear-gradient(135deg,var(--amber),#C97F22);color:#1A1200;box-shadow:0 4px 14px rgba(217,143,43,.28);}
  .btn-amber:hover{background:linear-gradient(135deg,#E6A448,var(--amber-dark));transform:translateY(-2px);box-shadow:0 10px 24px rgba(217,143,43,.4);}
  .btn-ghost{border-color:rgba(255,255,255,.28);color:#fff;}
  .btn-ghost:hover{border-color:#fff;background:rgba(255,255,255,.08);transform:translateY(-2px);}
  .btn-outline-dark{border-color:rgba(19,36,51,.25);color:var(--navy-900);}
  .btn-outline-dark:hover{background:rgba(19,36,51,.06);}

  /* ===== HERO ===== */
  .hero{
    position:relative;overflow:hidden;
    background:var(--navy-950);color:#fff;
    padding:96px 0 84px;
  }
  .hero .grid-bg{position:absolute;inset:0;opacity:.5;}
  .hero .glow{position:absolute;border-radius:50%;filter:blur(70px);pointer-events:none;z-index:1;}
  .hero .glow-amber{width:420px;height:420px;background:radial-gradient(circle,rgba(217,143,43,.28),transparent 70%);top:-120px;left:-100px;}
  .hero .glow-teal{width:380px;height:380px;background:radial-gradient(circle,rgba(63,122,92,.22),transparent 70%);bottom:-140px;right:10%;}
  .hero-inner{position:relative;z-index:2;display:grid;grid-template-columns:1.15fr .85fr;gap:56px;align-items:center;}
  .hero h1{font-size:clamp(30px,4vw,50px);line-height:1.28;margin:18px 0 20px;color:#fff;}
  .hero h1 span{background:linear-gradient(100deg,var(--amber),#F2C879 60%,var(--amber));-webkit-background-clip:text;background-clip:text;color:transparent;}
  .hero p.lead{font-size:17px;color:#B9C6D1;max-width:520px;margin-bottom:34px;}
  .hero-actions{display:flex;gap:14px;flex-wrap:wrap;}
  .hero-map{
    position:relative;aspect-ratio:1/1.02;border-radius:16px;
    background:linear-gradient(160deg,var(--navy-800),var(--navy-900));
    border:1px solid rgba(255,255,255,.1);
    overflow:hidden;
    box-shadow:0 24px 60px rgba(0,0,0,.4),0 0 0 1px rgba(255,255,255,.03);
    animation:floatMap 6s ease-in-out infinite;
  }
  @keyframes floatMap{0%,100%{transform:translateY(0);}50%{transform:translateY(-8px);}}
  .hero-map .live-badge{
    position:absolute;top:14px;left:14px;z-index:5;display:flex;align-items:center;gap:6px;
    background:rgba(11,32,51,.7);backdrop-filter:blur(6px);color:#fff;font-size:11px;font-weight:600;
    padding:5px 10px;border-radius:20px;border:1px solid rgba(255,255,255,.12);
    font-family:'JetBrains Mono',monospace;letter-spacing:.05em;
  }
  .hero-map .live-badge .lv-dot{width:6px;height:6px;border-radius:50%;background:#5FD98F;animation:pulseDot 1.6s ease-in-out infinite;}
  .pin{position:absolute;width:12px;height:12px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);background:var(--blueprint-soft);border:2px solid #0B2033;transition:transform .2s;}
  .pin.active{background:var(--amber);width:16px;height:16px;box-shadow:0 0 0 6px rgba(217,143,43,.22);z-index:3;animation:pinPulse 2.2s ease-in-out infinite;}
  @keyframes pinPulse{0%,100%{box-shadow:0 0 0 6px rgba(217,143,43,.22);}50%{box-shadow:0 0 0 11px rgba(217,143,43,.08);}}
  .popup{
    position:absolute;background:#fff;color:var(--ink);border-radius:8px;padding:12px 14px;
    width:186px;box-shadow:0 12px 30px rgba(0,0,0,.35);z-index:4;
    animation:popIn .5s cubic-bezier(.2,.9,.3,1.3) .3s both;
  }
  @keyframes popIn{from{opacity:0;transform:translateY(6px) scale(.94);}to{opacity:1;transform:translateY(0) scale(1);}}
  .popup .code{font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--muted);direction:ltr;text-align:right;}
  .popup .status{
    display:inline-flex;align-items:center;gap:6px;margin-top:8px;
    font-size:12px;font-weight:700;color:var(--green);background:var(--green-soft);
    padding:3px 9px;border-radius:20px;
  }
  .popup .status::before{content:"";width:6px;height:6px;border-radius:50%;background:var(--green);}

  /* ===== SECTION GENERIC ===== */
  section{padding:88px 0;}
  .section-head{max-width:640px;margin-bottom:52px;}
  .section-head h2{font-size:clamp(24px,2.6vw,34px);margin-top:14px;color:var(--navy-900);}
  .section-head p{color:var(--muted);font-size:16px;margin-top:14px;}

  /* ===== FEATURES ===== */
  .features-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:rgba(19,36,51,.12);border:1px solid rgba(19,36,51,.12);border-radius:var(--radius);overflow:hidden;}
  .feature{
    background:var(--paper);padding:34px 26px;position:relative;overflow:hidden;
    transition:background .3s ease,transform .3s ease;
  }
  .feature::before{
    content:"";position:absolute;top:0;right:0;left:0;height:3px;
    background:var(--accent,var(--amber));transform:scaleX(0);transform-origin:right;
    transition:transform .35s ease;
  }
  .feature:hover{background:#fff;transform:translateY(-4px);z-index:2;box-shadow:0 18px 34px rgba(15,42,67,.1);}
  .feature:hover::before{transform:scaleX(1);}
  .feature:nth-child(1){--accent:var(--amber);}
  .feature:nth-child(2){--accent:var(--blueprint-soft);}
  .feature:nth-child(3){--accent:var(--green);}
  .feature:nth-child(4){--accent:var(--navy-800);}
  .feature .icon-badge{
    width:52px;height:52px;border-radius:12px;margin-bottom:20px;
    display:flex;align-items:center;justify-content:center;
    background:color-mix(in srgb, var(--accent,var(--amber)) 14%, transparent);
    transition:transform .3s ease,background .3s ease;
  }
  .feature:hover .icon-badge{transform:scale(1.08) rotate(-4deg);}
  .feature .icon{width:26px;height:26px;color:var(--accent,var(--navy-900));}
  .feature h3{font-size:17px;color:var(--navy-900);margin-bottom:10px;}
  .feature p{font-size:14.5px;color:var(--muted);}

  /* ===== SHOWCASE (signature element) ===== */
  .showcase{background:var(--navy-950);color:#fff;}
  .showcase .section-head{max-width:640px;}
  .showcase .eyebrow{color:var(--amber);}
  .showcase .section-head h2{color:#fff;}
  .showcase .section-head p{color:#AEBCC7;}

  .showcase{position:relative;}
  .showcase-glow{
    position:absolute;left:50%;top:40%;width:640px;height:640px;
    background:radial-gradient(circle,rgba(217,143,43,.16),transparent 68%);
    transform:translate(-50%,-50%);pointer-events:none;filter:blur(10px);
  }
  .report-card{
    position:relative;background:#fff;color:var(--ink);border-radius:14px;overflow:hidden;
    box-shadow:0 30px 70px rgba(0,0,0,.4),0 0 0 1px rgba(255,255,255,.06);
    transition:transform .4s ease;
  }
  .report-card:hover{transform:translateY(-4px);}
  .report-meta{
    display:flex;justify-content:space-between;align-items:center;
    padding:18px 24px;border-bottom:1px dashed #D7DBD1;flex-wrap:wrap;gap:10px;
  }
  .report-meta .m-item{font-size:12.5px;color:var(--muted);}
  .report-meta .m-item b{display:block;color:var(--ink);font-size:14px;font-family:'JetBrains Mono',monospace;direction:ltr;text-align:right;}
  .compare{position:relative;width:100%;aspect-ratio:16/8;overflow:hidden;user-select:none;}
  .compare .layer{position:absolute;inset:0;}
  .compare .label{
    position:absolute;top:14px;font-size:12px;font-weight:700;
    background:rgba(11,32,51,.75);color:#fff;padding:4px 12px;border-radius:20px;
    font-family:'JetBrains Mono',monospace;letter-spacing:.05em;
  }
  .compare .label.before{right:14px;}
  .compare .label.after{left:14px;}
  .compare .after-layer{clip-path:inset(0 50% 0 0);}
  .compare .divider{
    position:absolute;top:0;bottom:0;width:3px;background:var(--amber);
    right:50%;transform:translateX(50%);z-index:5;pointer-events:none;
  }
  .compare .handle{
    position:absolute;top:50%;right:50%;transform:translate(50%,-50%);
    width:38px;height:38px;border-radius:50%;background:var(--amber);
    display:flex;align-items:center;justify-content:center;z-index:6;
    box-shadow:0 6px 16px rgba(0,0,0,.3);
  }
  .compare input[type=range]{
    position:absolute;inset:0;width:100%;height:100%;margin:0;
    opacity:0;cursor:ew-resize;z-index:7;
  }
  .report-footer{display:flex;justify-content:space-between;align-items:center;padding:18px 24px;background:#F7F8F4;}
  .report-footer .verdict{display:flex;align-items:center;gap:8px;font-weight:700;color:var(--green);font-size:14.5px;}
  .report-footer .verdict::before{content:"✓";display:flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:var(--green);color:#fff;font-size:12px;}
  .report-footer .note{font-size:13px;color:var(--muted);}

  /* ===== WORKFLOW ===== */
  .workflow-list{display:grid;grid-template-columns:repeat(4,1fr);gap:26px;position:relative;}
  .workflow-list::before{
    content:"";position:absolute;top:23px;right:12.5%;left:12.5%;height:1px;
    background:repeating-linear-gradient(to left,var(--blueprint) 0 8px,transparent 8px 16px);
    transform:scaleX(0);transform-origin:right;transition:transform 1.1s ease .2s;
  }
  .workflow-list.in::before{transform:scaleX(1);}
  .wf-step{position:relative;transition:transform .3s ease;}
  .wf-step:hover{transform:translateY(-4px);}
  .wf-num{
    width:46px;height:46px;border-radius:50%;background:var(--navy-900);color:#fff;
    display:flex;align-items:center;justify-content:center;font-family:'JetBrains Mono',monospace;
    font-weight:600;font-size:15px;margin-bottom:20px;position:relative;z-index:2;
    transition:background .3s ease,box-shadow .3s ease;
  }
  .wf-step:hover .wf-num{background:linear-gradient(135deg,var(--amber),var(--amber-dark));box-shadow:0 8px 18px rgba(217,143,43,.35);}
  .wf-step h3{font-size:15.5px;color:var(--navy-900);margin-bottom:8px;}
  .wf-step p{font-size:13.8px;color:var(--muted);}

  /* ===== TRUST ===== */
  .trust{background:var(--paper-dim);}
  .trust-grid{display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:center;}
  .trust h2{font-size:clamp(22px,2.4vw,30px);color:var(--navy-900);margin-bottom:16px;}
  .trust p{color:var(--muted);font-size:15.5px;margin-bottom:24px;}
  .tag-row{display:flex;flex-wrap:wrap;gap:10px;}
  .tag{
    font-size:13px;font-weight:600;color:var(--navy-800);background:#fff;
    border:1px solid rgba(19,36,51,.15);padding:8px 14px;border-radius:20px;
    transition:all .25s ease;cursor:default;
  }
  .tag:hover{border-color:var(--amber);color:var(--amber-dark);transform:translateY(-2px);box-shadow:0 6px 14px rgba(19,36,51,.08);}
  .stat-box{
    position:relative;overflow:hidden;background:var(--navy-900);color:#fff;border-radius:14px;padding:34px;
    box-shadow:0 24px 50px rgba(15,42,67,.25);
  }
  .stat-box::before{
    content:"";position:absolute;inset:0;opacity:.5;
    background-image:linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);
    background-size:26px 26px;pointer-events:none;
  }
  .stat-box .row{position:relative;display:flex;justify-content:space-between;padding:16px 0;border-bottom:1px solid rgba(255,255,255,.1);}
  .stat-box .row:last-child{border-bottom:none;}
  .stat-box .row span:first-child{color:#AEBCC7;font-size:14px;}
  .stat-box .row span:last-child{font-family:'JetBrains Mono',monospace;font-weight:600;color:var(--amber);}

  /* ===== CTA FINAL ===== */
  .cta-final{position:relative;overflow:hidden;background:var(--navy-950);color:#fff;text-align:center;}
  .cta-final::before{
    content:"";position:absolute;left:50%;top:50%;width:520px;height:520px;
    background:radial-gradient(circle,rgba(217,143,43,.18),transparent 70%);
    transform:translate(-50%,-50%);pointer-events:none;
  }
  .cta-final .wrap{position:relative;z-index:2;}
  .cta-final h2{font-size:clamp(24px,3vw,36px);color:#fff;margin-bottom:16px;}
  .cta-final p{color:#AEBCC7;max-width:480px;margin:0 auto 32px;font-size:16px;}
  .cta-actions{display:flex;justify-content:center;gap:14px;flex-wrap:wrap;}

  /* ===== FOOTER ===== */
  footer{background:var(--navy-900);color:#8FA1AF;padding:34px 0;font-size:13.5px;}
  .foot-row{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}

  /* reveal animation */
  .reveal{opacity:0;transform:translateY(18px);transition:opacity .6s ease,transform .6s ease;}
  .reveal.in{opacity:1;transform:translateY(0);}
  @media(prefers-reduced-motion:reduce){.reveal{opacity:1;transform:none;transition:none;}}

  @media(max-width:900px){
    .hero-inner{grid-template-columns:1fr;}
    .hero-map{max-width:420px;margin:0 auto;}
    .features-grid{grid-template-columns:1fr 1fr;}
    .workflow-list{grid-template-columns:1fr 1fr;row-gap:36px;}
    .workflow-list::before{display:none;}
    .trust-grid{grid-template-columns:1fr;}
    .nav-links{display:none;}
  }
  @media(max-width:560px){
    .features-grid{grid-template-columns:1fr;}
    .workflow-list{grid-template-columns:1fr;}
    section{padding:64px 0;}
  }
</style>
</head>
<body>
<div class="scroll-progress" id="scrollProgress"></div>

<header class="site-header">
  <nav class="nav">
    <div class="brand"><span class="dot"></span> پایش‌گر</div>
    <div class="nav-links">
      <a href="#features">امکانات</a>
      <a href="#showcase">بازبینی تصویری</a>
      <a href="#workflow">گردش کار</a>
      <a href="#trust">اعتماد سازمانی</a>
    </div>
    <a href="{{ route('login') }}" class="btn btn-amber">ورود کاربران</a>
  </nav>
</header>

<section class="hero">
  <div class="glow glow-amber"></div>
  <div class="glow glow-teal"></div>
  <svg class="grid-bg" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <pattern id="grid" width="46" height="46" patternUnits="userSpaceOnUse">
        <path d="M 46 0 L 0 0 0 46" fill="none" stroke="#2B5872" stroke-width="0.6" opacity="0.5"/>
      </pattern>
    </defs>
    <rect width="100%" height="100%" fill="url(#grid)"/>
  </svg>
  <div class="wrap hero-inner">
    <div>
      <span class="eyebrow">سامانه GIS تعمیرات تاسیسات</span>
      <h1>هر علمک، <span>یک نقطه</span> روی نقشه.<br>هر تعمیر، یک مستند قابل‌استناد.</h1>
      <p class="lead">پایش‌گر موقعیت دقیق تمام علمک‌های تحت پوشش را روی نقشه ثبت می‌کند و روند تعمیر را از عکس پیش از کار تا تأیید نهایی ناظر، گام‌به‌گام مستند نگه می‌دارد.</p>
      <div class="hero-actions">
        <a href="#contact" class="btn btn-amber">درخواست دمو</a>
        <a href="#showcase" class="btn btn-ghost">مشاهده روند بازبینی</a>
      </div>
    </div>
    <div class="hero-map">
      <div class="live-badge"><span class="lv-dot"></span> نقشه زنده</div>
      <svg width="100%" height="100%" viewBox="0 0 400 410" xmlns="http://www.w3.org/2000/svg">
        <path d="M40 60 L120 40 L210 90 L320 60 L360 140 L300 220 L340 300 L260 360 L150 340 L80 280 L50 190 Z" fill="none" stroke="#2B5872" stroke-width="1" opacity="0.7"/>
        <path d="M70 120 L180 150 L260 110 L330 190" fill="none" stroke="#3E6C86" stroke-width="1" stroke-dasharray="3 5" opacity="0.6"/>
      </svg>
      <div class="pin" style="top:22%;right:28%;"></div>
      <div class="pin" style="top:38%;right:60%;"></div>
      <div class="pin" style="top:58%;right:22%;"></div>
      <div class="pin" style="top:70%;right:48%;"></div>
      <div class="pin active" style="top:46%;right:42%;"></div>
      <div class="popup" style="top:20%;right:36%;">
        <div class="code">OLK-3391-B</div>
        <div class="status">تأیید ناظر</div>
      </div>
    </div>
  </div>
</section>

<section id="features">
  <div class="wrap">
    <div class="section-head reveal">
      <span class="eyebrow">امکانات</span>
      <h2>یک سامانه، از موقعیت‌یابی تا تأیید نهایی</h2>
      <p>چهار قابلیت اصلی که فرآیند تعمیر تاسیسات را از یک عملیات میدانی پراکنده، به یک جریان قابل ردیابی و قابل استناد تبدیل می‌کند.</p>
    </div>
    <div class="features-grid">
      <div class="feature reveal">
        <div class="icon-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 12 2a7 7 0 0 1 7 7.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg></div>
        <h3>نقشه زنده تاسیسات</h3>
        <p>موقعیت دقیق هر علمک با مختصات جغرافیایی روی نقشه ثبت و به‌صورت زنده قابل فیلتر بر اساس منطقه، وضعیت و تاریخ آخرین تعمیر است.</p>
      </div>
      <div class="feature reveal">
        <div class="icon-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="4" width="8" height="8" rx="1"/><rect x="13" y="12" width="8" height="8" rx="1"/><path d="M11 8h6M13 16H5"/></svg></div>
        <h3>مقایسه تصویری پیش و پس از تعمیر</h3>
        <p>ناظر تصاویر ثبت‌شده در دو مرحله را کنار هم بررسی می‌کند و بر اساس آن، تأیید یا رد کیفیت تعمیر را ثبت می‌کند.</p>
      </div>
      <div class="feature reveal">
        <div class="icon-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 2 3 7l9 5 9-5-9-5z"/><path d="M3 12l9 5 9-5M3 17l9 5 9-5"/></svg></div>
        <h3>ثبت مصالح مصرفی</h3>
        <p>ریز اقلام و مقدار مصالح استفاده‌شده در هر تعمیر ثبت می‌شود و در گزارش نهایی هر علمک قابل مشاهده و استناد است.</p>
      </div>
      <div class="feature reveal">
        <div class="icon-badge"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
        <h3>گردش کار تأیید ناظر</h3>
        <p>هر تعمیر مسیری مشخص از ثبت پیمانکار تا بررسی و تأیید نهایی ناظر طی می‌کند؛ بدون امضای ناظر، پرونده بسته نمی‌شود.</p>
      </div>
    </div>
  </div>
</section>

<section id="showcase" class="showcase">
  <div class="wrap">
    <div class="section-head reveal">
      <span class="eyebrow">بازبینی تصویری</span>
      <h2>تصویر، مدرکِ کیفیتِ کار است</h2>
      <p>اسلایدر زیر را جابه‌جا کنید تا ببینید ناظر چگونه وضعیت علمک را پیش و پس از تعمیر مقایسه می‌کند.</p>
    </div>

    <div class="showcase-glow"></div>
    <div class="report-card reveal">
      <div class="report-meta">
        <div class="m-item">کد علمک <b>OLK-3391-B</b></div>
        <div class="m-item">منطقه <b style="direction:rtl;font-family:'Vazirmatn'">شهرک صنعتی — بلوک ۴</b></div>
        <div class="m-item">تاریخ تعمیر <b>1404/04/28</b></div>
        <div class="m-item">ناظر <b style="direction:rtl;font-family:'Vazirmatn'">مهندس رضایی</b></div>
      </div>

      <div class="compare" id="compareWidget">
        <div class="layer before-layer">
          <svg width="100%" height="100%" viewBox="0 0 800 400" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
            <rect width="800" height="400" fill="#EFE9DD"/>
            <rect x="0" y="330" width="800" height="70" fill="#D8CFBB"/>
            <rect x="360" y="90" width="80" height="250" fill="#B7B0A0" stroke="#8A8272" stroke-width="2"/>
            <rect x="345" y="70" width="110" height="30" fill="#9B937F" stroke="#7A745F" stroke-width="2"/>
            <path d="M370 130 l15 20 -20 25 20 20 -15 25" fill="none" stroke="#8B4B2E" stroke-width="3" opacity="0.7"/>
            <circle cx="420" cy="220" r="5" fill="#8B4B2E" opacity="0.6"/>
            <circle cx="390" cy="270" r="4" fill="#8B4B2E" opacity="0.6"/>
            <path d="M330 340 q20 -10 40 0 t40 0 t40 0 t40 0" stroke="#A79E8A" stroke-width="2" fill="none" opacity="0.6"/>
          </svg>
        </div>
        <div class="layer after-layer">
          <svg width="100%" height="100%" viewBox="0 0 800 400" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
            <rect width="800" height="400" fill="#EFE9DD"/>
            <rect x="0" y="330" width="800" height="70" fill="#D8CFBB"/>
            <rect x="360" y="90" width="80" height="250" fill="#E8B93A" stroke="#B8721B" stroke-width="2"/>
            <rect x="345" y="70" width="110" height="30" fill="#D98F2B" stroke="#B8721B" stroke-width="2"/>
            <circle cx="400" cy="200" r="3" fill="#8A6C1E" opacity="0.5"/>
            <path d="M330 340 q20 -10 40 0 t40 0 t40 0 t40 0" stroke="#3F7A5C" stroke-width="2.5" fill="none"/>
          </svg>
        </div>
        <span class="label before">پیش از تعمیر</span>
        <span class="label after">پس از تعمیر</span>
        <div class="divider" id="divider"></div>
        <div class="handle" id="handle">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0B2033" stroke-width="2.4"><path d="M8 7l-5 5 5 5M16 7l5 5-5 5"/></svg>
        </div>
        <input type="range" id="compareRange" min="0" max="100" value="50" aria-label="مقایسه تصویر پیش و پس از تعمیر">
      </div>

      <div class="report-footer">
        <span class="verdict">تأیید شد — تعویض رایزر و رنگ‌آمیزی استاندارد</span>
        <span class="note">مصالح مصرفی: لوله فولادی ۲″، رنگ ضدزنگ زرد، بست فلزی ×۲</span>
      </div>
    </div>
  </div>
</section>

<section id="workflow">
  <div class="wrap">
    <div class="section-head reveal">
      <span class="eyebrow">گردش کار</span>
      <h2>از ثبت میدانی تا آرشیو مستند</h2>
      <p>هر پرونده تعمیر چهار مرحله ثابت را طی می‌کند تا از دقت مکانی و صحت اجرا اطمینان حاصل شود.</p>
    </div>
    <div class="workflow-list reveal">
      <div class="wf-step reveal">
        <div class="wf-num">۰۱</div>
        <h3>ثبت علمک روی نقشه</h3>
        <p>موقعیت دقیق و مشخصات فنی علمک در بستر GIS ثبت می‌شود.</p>
      </div>
      <div class="wf-step reveal">
        <div class="wf-num">۰۲</div>
        <h3>اجرای تعمیر و ثبت تصویر</h3>
        <p>پیمانکار عکس پیش و پس از کار را به همراه مصالح مصرفی ثبت می‌کند.</p>
      </div>
      <div class="wf-step reveal">
        <div class="wf-num">۰۳</div>
        <h3>بررسی ناظر</h3>
        <p>ناظر تصاویر را مقایسه و کیفیت اجرا را ارزیابی می‌کند.</p>
      </div>
      <div class="wf-step reveal">
        <div class="wf-num">۰۴</div>
        <h3>تأیید نهایی و آرشیو</h3>
        <p>پرونده با امضای دیجیتال ناظر بسته و در سابقه علمک آرشیو می‌شود.</p>
      </div>
    </div>
  </div>
</section>

<section id="trust" class="trust">
  <div class="wrap trust-grid">
    <div class="reveal">
      <span class="eyebrow">اعتماد سازمانی</span>
      <h2>طراحی‌شده برای زیرساخت‌های حساس</h2>
      <p>پایش‌گر برای پاسخ‌گویی به نیاز مستندسازی و ردیابی دقیق در شرکت‌های توزیع گاز، آب و برق ساخته شده و با ساختار سازمانی و سلسله‌مراتب تأیید در این نهادها هم‌خوان است.</p>
      <div class="tag-row">
        <span class="tag">سازگار با ساختار سلسله‌مراتبی تأیید</span>
        <span class="tag">آرشیو دائمی مستندات</span>
        <span class="tag">دسترسی مبتنی بر نقش کاربر</span>
        <span class="tag">خروجی گزارش قابل ارائه به بازرس</span>
      </div>
    </div>
    <div class="stat-box reveal">
      <div class="row"><span>ثبت موقعیت</span><span>مختصات دقیق GPS</span></div>
      <div class="row"><span>مستندسازی تصویری</span><span>پیش / پس از تعمیر</span></div>
      <div class="row"><span>سطوح تأیید</span><span>پیمانکار ← ناظر ← آرشیو</span></div>
      <div class="row"><span>دسترسی</span><span>وب و موبایل</span></div>
    </div>
  </div>
</section>

<section class="cta-final" id="contact">
  <div class="wrap">
    <span class="eyebrow" style="color:var(--amber)">شروع کنید</span>
    <h2 style="margin-top:14px;">برای دیدن دموی اختصاصی سامانه در تماس باشید</h2>
    <p>تیم ما می‌تواند سامانه را با داده واقعی منطقه شما پیاده‌سازی و در یک جلسه کوتاه نمایش دهد.</p>
    <div class="cta-actions">
      <a href="mailto:demo@payeshgar.example" class="btn btn-amber">ارسال درخواست دمو</a>
      <a href="#features" class="btn btn-ghost">بازگشت به امکانات</a>
    </div>
  </div>
</section>

<footer>
  <div class="wrap foot-row">
    <span>© ۱۴۰۴ پایش‌گر — سامانه نظارت بر تعمیرات تاسیسات</span>
    <span class="mono" style="direction:rtl;font-family:'Vazirmatn';color:#8FA1AF;">ساخته‌شده برای زیرساخت‌های شهری</span>
  </div>
</footer>

<script>
  // Scroll progress bar
  const progressBar = document.getElementById('scrollProgress');
  function updateProgress(){
    const h = document.documentElement;
    const scrolled = (h.scrollTop) / (h.scrollHeight - h.clientHeight) * 100;
    progressBar.style.width = scrolled + '%';
  }
  document.addEventListener('scroll', updateProgress);
  updateProgress();

  // Before/after compare slider
  const range = document.getElementById('compareRange');
  const afterLayer = document.querySelector('.after-layer');
  const divider = document.getElementById('divider');
  const handle = document.getElementById('handle');
  function updateCompare(val){
    afterLayer.style.clipPath = `inset(0 ${100-val}% 0 0)`;
    divider.style.right = val + '%';
    handle.style.right = val + '%';
  }
  range.addEventListener('input', e => updateCompare(e.target.value));
  updateCompare(50);

  // Reveal on scroll
  const revealEls = document.querySelectorAll('.reveal');
  const io = new IntersectionObserver((entries)=>{
    entries.forEach(en=>{ if(en.isIntersecting){ en.target.classList.add('in'); io.unobserve(en.target);} });
  }, {threshold:0.15});
  revealEls.forEach(el=>io.observe(el));
</script>

</body>
</html>
