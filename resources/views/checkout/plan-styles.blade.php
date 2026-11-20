<style>
    :root{color-scheme:light;--ink:#101d3e;--muted:#485b78;--line:#dae4f4;--blue:#173cf3;--mint:#2de0ce;--surface:#f2f6fd}
    *{box-sizing:border-box}
    body{margin:0;color:var(--ink);background:var(--surface);font:1rem/1.5 "Aptos","Segoe UI",system-ui,sans-serif}
    a{color:inherit}
    button,input{font:inherit}
    p,h1,h2,h3{margin:0}
    :focus-visible{outline:3px solid #264eee;outline-offset:4px}
    .page-width{width:min(1160px,calc(100% - 56px));margin-inline:auto}
    .skip-link{position:absolute;z-index:1;left:12px;top:12px;padding:10px;background:#fff;transform:translateY(-160%)}
    .skip-link:focus{transform:none}
    .site-header{background:#fff;border-bottom:1px solid var(--line)}
    .header-content,.header-links,.footer-content{display:flex;align-items:center;justify-content:space-between;gap:22px}
    .header-content{min-height:66px}
    .brand{display:inline-flex;align-items:center;gap:10px;font-size:1.12rem;font-weight:850;white-space:nowrap;text-decoration:none}
    .brand img{object-fit:contain}
    .header-links a{font-size:.89rem;font-weight:700;text-decoration:none}
    .header-links a:hover{text-decoration:underline}
    .account-link{padding:8px 14px;border:1px solid #cddaff;border-radius:9px;color:#1839be}
    .hero{color:#fff;background:radial-gradient(ellipse at 94% 98%,#622fd2 0,transparent 66%),linear-gradient(111deg,#0a1438 4%,#142f90 69%,#194890)}
    .hero-layout{display:grid;grid-template-columns:minmax(0,1.32fr) minmax(0,1fr);align-items:center;gap:clamp(26px,6vw,82px);padding-block:clamp(38px,6vw,76px)}
    .eyebrow{display:flex;align-items:center;gap:10px;color:#a3fff3;font-size:.8rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
    .eyebrow span{width:8px;height:8px;background:var(--mint);border-radius:50%}
    h1{margin-top:10px;max-width:15ch;font-size:clamp(2.3rem,5vw,4.5rem);line-height:1.01;letter-spacing:-.055em;font-weight:800}
    h1 em{color:var(--mint);font-style:normal}
    .hero-copy{color:#dce6ff;max-width:46ch;margin-top:16px;font-size:clamp(.98rem,1.45vw,1.13rem)}
    .email-panel{padding:clamp(18px,3vw,29px);border:1px solid #dbe5fa;border-radius:19px;background:#fff;color:var(--ink);box-shadow:0 16px 36px #07154855}
    .email-panel label{display:block;font-size:1.19rem;font-weight:800}
    .email-panel p{margin:.35rem 0 .85rem;font-size:.92rem;color:var(--muted)}
    .email-panel input{width:100%;min-height:49px;padding:10px 12px;color:var(--ink);background:#f6f8fe;border:1px solid #a7b9db;border-radius:9px}
    .email-panel input[aria-invalid="true"]{border-color:#a81838}
    .email-panel .form-error{color:#a81838;margin:.55rem 0 0}
    .plans{padding-top:30px;padding-bottom:42px}
    .plans-heading{display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:17px}
    .plans-heading h2{font-size:clamp(1.55rem,3vw,2.2rem);font-weight:800;letter-spacing:-.04em;line-height:1.15}
    .plans-heading p,.plan-description{color:var(--muted);font-size:.9rem}
    .plan-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px}
    .plan-card{min-width:0;display:flex;flex-direction:column;padding:clamp(17px,2.3vw,28px);background:#fff;border:1px solid var(--line);border-radius:20px;box-shadow:0 8px 23px #1e3b7e0a}
    .plan-card-accent{background:linear-gradient(180deg,#e5f7ff,#fff 75%);border-color:#8cb4f6}
    .plan-type{font-size:.76rem;text-transform:uppercase;font-weight:850;letter-spacing:.09em;color:#193cb0}
    .plan-type-local{color:#5228aa}
    h3{margin-top:3px;font-size:clamp(1.33rem,2.4vw,1.8rem);font-weight:820;letter-spacing:-.045em}
    .price{display:flex;align-items:baseline;flex-wrap:wrap;gap:4px 8px;line-height:1.15;margin-top:18px}
    .price strong{font-size:clamp(1.9rem,3.2vw,2.8rem);font-weight:850;letter-spacing:-.055em}
    .price span,.billing{font-size:.86rem;color:var(--muted)}
    .billing{margin-top:6px}
    .features{list-style:none;padding:13px 0 0;margin:16px 0 20px;border-top:1px solid var(--line);font-size:.89rem}
    .features li{position:relative;padding-left:20px}
    .features li+li{margin-top:5px}
    .features li::before{content:"";position:absolute;left:2px;top:.4em;width:9px;height:5px;border-left:2px solid #087c72;border-bottom:2px solid #087c72;transform:rotate(-45deg)}
    .choose{margin-top:auto;min-height:48px;padding:9px 12px;text-align:center;border:1px solid #b6c8f9;border-radius:10px;color:#1737bd;background:#fff;font-weight:800;cursor:pointer}
    .choose:hover{background:#eaf0ff}
    .choose-accent{background:var(--blue);color:#fff;border-color:var(--blue)}
    .choose-accent:hover{background:#142bb8}
    .unavailable{margin-top:auto;font-size:.88rem;color:var(--muted)}
    .site-footer{background:#e9eff9;border-top:1px solid var(--line);color:var(--muted);font-size:.87rem}
    .footer-content{min-height:60px}
    @media(max-width:870px){.plan-grid{grid-template-columns:repeat(2,minmax(0,1fr));}.hero-layout{gap:26px}}
    @media(max-width:640px){
        .page-width{width:calc(100% - 32px)}
        .hero-layout,.plan-grid{grid-template-columns:minmax(0,1fr)}
        .hero-layout{gap:23px}
        .header-links{gap:12px}
        .header-links a{font-size:.8rem}
        .brand{gap:5px;font-size:1rem}
        .brand img{width:29px;height:29px}
        .plans-heading{align-items:flex-start;flex-direction:column;gap:4px}
    }
</style>
