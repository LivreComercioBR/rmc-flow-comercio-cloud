<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class AccountPasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.password-change');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();
        $request->session()->regenerate();

        return back()->with('password_notice', 'Senha alterada. Use a nova senha no próximo acesso.');
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user->getKey())],
            'current_password' => ['required', 'current_password'],
        ]);

        if ($data['email'] === mb_strtolower($user->email)) {
            return back()->with('email_notice', 'Este e-mail já está cadastrado nesta conta.');
        }

        $user->forceFill([
            'email' => $data['email'],
            'email_verified_at' => null,
        ])->save();
        $request->session()->regenerate();

        return redirect()->route('verification.notice')
            ->with('status', 'E-mail de acesso atualizado. Confirme o novo endereço para continuar usando o painel; nenhum aviso foi enviado ao e-mail antigo.');
    }
}
