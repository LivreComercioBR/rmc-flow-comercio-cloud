<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ChangeUnverifiedEmailController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('subscription.status')->with('status', 'Seu e-mail j\u{00E1} est\u{00E1} confirmado. A troca por esta tela s\u{00F3} est\u{00E1} dispon\u{00ED}vel antes da primeira confirma\u{00E7}\u{00E3}o.');
        }

        $newEmail = $request->input('new_email');
        $request->merge([
            'new_email' => is_string($newEmail) ? Str::lower(trim($newEmail)) : $newEmail,
        ]);

        $data = $request->validate([
            'new_email' => ['required', 'string', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['current_password'], $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'current_password' => 'A senha informada est� incorreta.',
            ]);
        }

        if ($data['new_email'] === Str::lower($user->email)) {
            throw ValidationException::withMessages([
                'new_email' => 'Informe um endere�o diferente do e-mail atual.',
            ]);
        }

        $user->forceFill([
            'email' => $data['new_email'],
            'email_verified_at' => null,
        ])->save();

        $request->session()->regenerate();

        if (! in_array(config('mail.default'), ['smtp', 'ses', 'ses-v2', 'mailgun', 'postmark', 'resend', 'sendmail'], true)) {
            return redirect()->route('verification.notice')->withErrors([
                'email' => 'O endere�o foi alterado, mas o envio de e-mail n�o est� configurado. Entre em contato com o suporte para receber o link de verifica��o.',
            ]);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            Log::warning('Verification email delivery failed after address change.', [
                'exception_type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('verification.notice')->withErrors([
                'email' => 'O endere�o foi alterado, mas n�o foi poss�vel enviar o link agora. Tente reenvi�-lo mais tarde.',
            ]);
        }

        return redirect()->route('verification.notice')->with('status', 'E-mail atualizado. Enviamos um novo link de verifica��o para o endere�o informado.');
    }
}
