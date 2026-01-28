<?php

namespace App\Http\Controllers\Backoffice;

use App\Domain\Identity\Models\ValueObjects\UserType;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Backoffice/Auth/Login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Check if user exists and is admin
        $user = \App\Models\User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password_hash)) {
            throw ValidationException::withMessages([
                'email' => ['Les informations d\'identification fournies sont incorrectes.'],
            ]);
        }

        if ($user->user_type !== UserType::ADMIN()->getValue()) {
            throw ValidationException::withMessages([
                'email' => ['Accès non autorisé. Seuls les administrateurs peuvent accéder au backoffice.'],
            ]);
        }

        if ($user->account_status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'email' => ['Votre compte est suspendu. Contactez l\'administrateur.'],
            ]);
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended('/backoffice/dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/backoffice/login');
    }
}
