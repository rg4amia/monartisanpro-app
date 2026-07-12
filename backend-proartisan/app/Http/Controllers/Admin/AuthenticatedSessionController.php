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
    protected \App\Services\Google2faService $google2faService;

    public function __construct(?\App\Services\Google2faService $google2faService = null)
    {
        $this->google2faService = $google2faService ?? app(\App\Services\Google2faService::class);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            if ($request->user()->role === 'admin') {
                return redirect()->route('admin.dashboard');
            }
            if ($request->user()->role === 'fournisseur') {
                return redirect()->route('supplier.dashboard');
            }
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

        // Store user ID and remember preference temporarily in session
        session([
            'admin_2fa_user_id' => $user->id,
            'admin_2fa_remember' => (bool) ($credentials['remember'] ?? false),
        ]);

        return redirect()->route('admin.login.verify-2fa');
    }

    public function showVerify2fa(Request $request): Response|RedirectResponse
    {
        $userId = session('admin_2fa_user_id');
        if (!$userId) {
            return redirect()->route('admin.login')
                ->with('error', 'Veuillez d’abord saisir vos identifiants.');
        }

        $user = User::findOrFail($userId);

        if (!$user->google_2fa_secret) {
            $tempSecret = session('admin_2fa_temp_secret');
            if (!$tempSecret) {
                $tempSecret = $this->google2faService->generateSecretKey();
                session(['admin_2fa_temp_secret' => $tempSecret]);
            }

            $qrCodeUrl = $this->google2faService->getQrCodeUrl($user->email ?? $user->phone, $tempSecret);
            $qrCodeImgUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrCodeUrl);

            return Inertia::render('admin/auth/verify-2fa', [
                'isConfigured' => false,
                'secret' => $tempSecret,
                'qrCodeUrl' => $qrCodeImgUrl,
            ]);
        }

        return Inertia::render('admin/auth/verify-2fa', [
            'isConfigured' => true,
        ]);
    }

    public function verify2fa(Request $request): RedirectResponse
    {
        $userId = session('admin_2fa_user_id');
        if (!$userId) {
            return redirect()->route('admin.login');
        }

        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ], [
            'code.required' => 'Le code de validation est obligatoire.',
            'code.size' => 'Le code doit comporter exactement 6 chiffres.',
        ]);

        $user = User::findOrFail($userId);

        $isNewRegistration = !$user->google_2fa_secret;
        $secret = $isNewRegistration 
            ? session('admin_2fa_temp_secret') 
            : $user->google_2fa_secret;

        if (!$secret || !$this->google2faService->verifyCode($secret, $request->code)) {
            throw ValidationException::withMessages([
                'code' => 'Le code de validation est incorrect.',
            ]);
        }

        if ($isNewRegistration) {
            $user->update(['google_2fa_secret' => $secret]);
        }

        Auth::guard('web')->login($user, (bool) session('admin_2fa_remember', false));

        session()->forget(['admin_2fa_user_id', 'admin_2fa_remember', 'admin_2fa_temp_secret']);
        $request->session()->regenerate();

        $redirectRoute = $user->role === 'fournisseur' ? 'supplier.dashboard' : 'admin.dashboard';
        return redirect()->intended(route($redirectRoute))
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
