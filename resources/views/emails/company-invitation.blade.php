<p>Olá,</p>
<p>Você foi convidado(a) para acessar a empresa <strong>{{ $invitation->company->trade_name }}</strong> no RMC Flow Comércio Cloud, com o perfil <strong>{{ $invitation->role }}</strong>.</p>
<p><a href="{{ $url }}">Aceitar convite</a></p>
<p>O convite expira em {{ $invitation->expires_at->format('d/m/Y H:i') }}. Se você não esperava este convite, ignore esta mensagem.</p>
