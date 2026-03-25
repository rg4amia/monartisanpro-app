<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        return Inertia::render('admin/auth/login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(
            [
                'identifier' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:6'],
                'remember' => ['nullable', 'boolean'],
            ],
            [
                'identifier.required' => 'L’adresse e-mail ou le téléphone est obligatoire.',
                'password.required' => 'Le mot de passe est obligatoire.',
                'password.min' => 'Le mot de passe doit contenir au moins 6 caractères.',
            ],
        );

        $identifier = trim((string) $credentials['identifier']);

        $user = User::query()
            ->where('phone', $identifier)
            ->orWhere('email', Str::lower($identifier))
            ->first();

        if (! $user || ! $user->password || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => 'Identifiants invalides.',
            ]);
        }

        if ($user->role !== 'admin') {
            throw ValidationException::withMessages([
                'identifier' => 'Accès réservé aux administrateurs.',
            ]);
        }

        Auth::guard('web')->login($user, (bool) ($credentials['remember'] ?? false));
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'))
            ->with('success', 'Connexion réussie.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')
            ->with('success', 'Déconnexion réussie.');
    }
}
