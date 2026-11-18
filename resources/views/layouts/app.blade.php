<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'RMC Flow Comércio Cloud' }}</title>
    <style>
        :root{font-family:Inter,Segoe UI,Arial,sans-serif;color:#15213b;background:#f4f7fb;font-synthesis:none}*{box-sizing:border-box}body{margin:0;min-height:100vh}.top{height:68px;background:#081b35;color:white;display:flex;align-items:center;justify-content:space-between;padding:0 max(22px,calc((100% - 1120px)/2))}.brand{font-weight:750;font-size:20px;color:#43d1d9;text-decoration:none}.top small{color:#c9d7e8}.container{width:min(1120px,calc(100% - 32px));margin:34px auto}.card{background:white;border:1px solid #e1e8f0;border-radius:14px;padding:28px;box-shadow:0 10px 30px #0a244012}.dashboard-card{background:#078fa8;border-color:#067d94}.dashboard-card>.title{color:white}.dashboard-card>.muted{color:#e1f5f8}.dashboard-card .company{background:#f7fbff;border-color:#c5d8ef}.dashboard-card .company .muted{color:#58708b}.narrow{max-width:560px;margin:44px auto}.title{margin:0 0 8px;font-size:28px;color:#102c4d}.muted{color:#66758b;margin:0 0 22px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.field{margin-bottom:16px}.field label{display:block;font-size:14px;font-weight:650;margin-bottom:6px}.field input{width:100%;height:44px;border:1px solid #cbd5e1;border-radius:8px;padding:0 12px;font:inherit}.field input:focus{outline:2px solid #8acde0;border-color:#138bab}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;border:0;border-radius:8px;padding:0 18px;background:#0f3cff;color:white;text-decoration:none;font:inherit;font-weight:650;cursor:pointer}.btn:hover{background:#1231c7}.btn.secondary{background:#e9f0ff;color:#123cbd}.wide{width:100%}.error{color:#b42318;font-size:13px;margin-top:5px}.alert{background:#eef6ff;color:#16436d;border:1px solid #c8ddf3;border-radius:8px;padding:12px;margin:14px 0}.alert-success{background:#ecfdf3;color:#176b3a;border-color:#a6e6bd}.alert-danger{background:#fff1f0;color:#912018;border-color:#ffdad6}.alert-warming,.alert-warning{background:#fff8e1;color:#735600;border-color:#ffe08a}.actions{display:flex;gap:10px;align-items:center}.company{display:flex;justify-content:space-between;align-items:center;border:1px solid #e1e8f0;border-radius:10px;padding:16px;margin-top:12px}.company h3{margin:0 0 5px}.footer{color:#77859a;text-align:center;font-size:13px;margin:24px 0}@media(max-width:640px){.grid{grid-template-columns:1fr;gap:0}.card{padding:22px}.title{font-size:24px}.company{align-items:flex-start;gap:12px;flex-direction:column}.top{padding:0 16px}}
    
        .actions{flex-wrap:wrap}.dashboard-nav{display:grid;grid-template-columns:repeat(auto-fit,minmax(135px,1fr));gap:10px;margin-top:28px}.dashboard-nav>.btn,.dashboard-nav>form,.dashboard-nav>form .btn{width:100%;min-width:0}.btn{text-align:center;white-space:normal;line-height:1.25;min-height:46px;padding:8px 14px}@media(max-width:640px){.top{height:auto;min-height:68px;padding:12px 16px;gap:12px}.dashboard-nav{grid-template-columns:repeat(auto-fit,minmax(125px,1fr))}}
    </style>
</head>
<body>
<header class="top"><a class="brand" href="{{ auth()->check() ? route('companies.index') : route('checkout.plans') }}">RMC Flow Comércio Cloud</a>@auth<div class="actions"><a href="{{ request()->routeIs('dashboard') ? route('company.settings.edit') : route('dashboard') }}" style="color:#d9f7fb;text-decoration:none">{{ request()->routeIs('dashboard') ? 'Dados da empresa' : 'Painel' }}</a><small>{{ auth()->user()->name }}</small></div>@endauth</header>
<main class="container">@yield('content')</main>
<footer class="footer">RMC Sites · Gestão comercial online</footer>
</body>
</html>

