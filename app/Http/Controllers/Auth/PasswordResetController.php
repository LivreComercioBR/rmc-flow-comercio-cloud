<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

final class PasswordResetController extends Controller
{
    public function requestForm(): View { return view('auth.password-email'); }

    public function sendLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:190']]);
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->withErrors(['email' => "O envio de e-mail ainda n\u{00E3}o est\u{00E1} configurado. Entre em contato com o suporte."])->onlyInput('email');
        }

        $data['email'] = mb_strtolower(trim($data['email']));

        try {
            $status = Password::sendResetLink($data);
        } catch (Throwable $exception) {
            Log::error('Password reset email delivery failed.', [
                'exception_type' => get_class($exception),
                'email_hash' => hash('sha256', $data['email']),
            ]);

            return back()
                ->withErrors(['email' => 'Não foi possível enviar o link agora. Tente novamente mais tarde.'])
                ->onlyInput('email');
        }

        $logContext = [
            'broker_status' => $status,
            'email_hash' => hash('sha256', $data['email']),
        ];

        if ($status === Password::RESET_LINK_SENT) {
            Log::info('Password reset request accepted by the mail transport.', $logContext);
        } else {
            Log::notice('Password reset link was not sent.', $logContext);
        }

        // Keep the same success response for existing and unknown addresses to prevent account discovery.
        return back()->with('status', "Se o e-mail estiver cadastrado, enviaremos um link de redefini\u{00E7}\u{00E3}o. Verifique tamb\u{00E9}m a caixa de spam.");
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.password-reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);
        $data['email'] = mb_strtolower(trim($data['email']));

        $status = Password::reset($data, function ($user, string $password): void {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Senha redefinida. Entre com sua nova senha.')
            : back()->withErrors(['email' => "O link expirou ou n\u{00E3}o \u{00E9} v\u{00E1}lido. Solicite outro link."])->withInput($request->only('email'));
    }
}
