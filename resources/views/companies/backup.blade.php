@extends('layouts.app', ['title' => 'Backup da empresa | RMC Flow Comércio Cloud'])
@section('content')
<section class="card">
    <div class="actions" style="justify-content:space-between;flex-wrap:wrap">
        <div><p class="muted" style="margin-bottom:8px">CONFIGURAÇÕES</p><h1 class="title">Backup da empresa</h1><p class="muted">Baixe uma cópia dos dados comerciais desta empresa ou restaure um backup criado por ela.</p></div>
        <div class="actions"><a class="btn secondary" href="{{ route('company.settings.edit') }}">Configurações</a><a class="btn secondary" href="{{ route('dashboard') }}">Painel</a></div>
    </div>

    @if(session('backup_notice'))<div class="alert alert-success" role="status">{{ session('backup_notice') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

    <div style="border:1px solid #dbe5f0;border-radius:10px;padding:20px;margin-top:20px">
        <h2 style="margin-top:0">Criar backup</h2>
        <p class="muted">O arquivo inclui os dados comerciais e as configurações da empresa ativa. Guarde-o em um local seguro.</p>
        <a class="btn" href="{{ route('company.backup.download') }}">Baixar backup</a>
    </div>

    <div style="border:1px solid #f1c8c4;border-radius:10px;padding:20px;margin-top:18px">
        <h2 style="margin-top:0">Restaurar backup</h2>
        <p class="alert alert-warming">A restauração substitui os dados comerciais atuais pelos dados do arquivo e remove alterações feitas depois da data do backup. Usuários, auditoria, assinaturas e cobranças não são alterados. Feche o caixa antes de restaurar.</p>
        <form method="post" action="{{ route('company.backup.restore') }}" enctype="multipart/form-data" style="max-width:650px">
            @csrf
            <div class="field"><label for="backup_file">Arquivo de backup (.json)</label><input id="backup_file" name="backup_file" type="file" accept=".json,application/json" required>@error('backup_file')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="confirmation">Digite RESTAURAR para confirmar</label><input id="confirmation" name="confirmation" autocomplete="off" required>@error('confirmation')<div class="error">{{ $message }}</div>@enderror</div>
            <button class="btn" type="submit" onclick="return confirm('A restauração substituirá os dados comerciais atuais desta empresa. Continuar?')">Restaurar backup</button>
        </form>
    </div>
</section>
@endsection
