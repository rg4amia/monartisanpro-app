<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\User;
use App\Services\Admin\AdminLoginThrottle;
use App\Services\Admin\AdminPermissionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminResetPasswordCommand extends Command
{
    protected $signature = 'admin:reset-password
                            {identifier : Email ou téléphone de l\'administrateur}
                            {password? : Nouveau mot de passe (défaut : admin123)}
                            {--reset-2fa : Réinitialiser le secret 2FA pour permettre une nouvelle configuration}';

    protected $description = 'Définit ou réinitialise le mot de passe d\'un administrateur (et optionnellement son 2FA).';

    public function handle(AdminLoginThrottle $throttle, AdminPermissionService $permissions): int
    {
        $identifier = trim((string) $this->argument('identifier'));
        $password = (string) ($this->argument('password') ?: 'admin123');
        $reset2fa = (bool) $this->option('reset-2fa');

        $normalizedIdentifier = Str::lower($identifier);
        $lookupEmails = [$normalizedIdentifier];
        if (str_ends_with($normalizedIdentifier, '@prosartisan.com')) {
            $lookupEmails[] = str_replace('@prosartisan.com', '@prosartisan.ci', $normalizedIdentifier);
            $lookupEmails[] = str_replace('@prosartisan.com', '@prosartisan.net', $normalizedIdentifier);
        } elseif (str_ends_with($normalizedIdentifier, '@prosartisan.ci')) {
            $lookupEmails[] = str_replace('@prosartisan.ci', '@prosartisan.com', $normalizedIdentifier);
            $lookupEmails[] = str_replace('@prosartisan.ci', '@prosartisan.net', $normalizedIdentifier);
        }

        $user = User::query()
            ->where('phone', $identifier)
            ->orWhereIn('email', $lookupEmails)
            ->first();

        if (! $user) {
            $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
            $this->warn("L'administrateur [{$identifier}] n'existe pas encore.");

            if (! $this->confirm("Voulez-vous créer ce compte administrateur maintenant ?", true)) {
                return self::FAILURE;
            }

            $user = User::create([
                'name' => 'Administrateur '.($isEmail ? Str::before($identifier, '@') : $identifier),
                'email' => $isEmail ? $identifier : null,
                'phone' => ! $isEmail ? $identifier : '+2250000000000',
                'password' => Hash::make($password),
                'role' => 'admin',
                'kyc_status' => 'actif',
            ]);

            $this->info("Compte administrateur créé avec l'ID {$user->id}.");
        } else {
            $user->password = Hash::make($password);
            if ($user->role !== 'admin') {
                $user->role = 'admin';
            }
            $user->kyc_status = 'actif';

            if ($reset2fa) {
                $user->google_2fa_secret = null;
            }

            $user->save();
        }

        // Attribution inconditionnelle de l'accès total
        $fullAccessId = Permission::where('name', AdminPermissionService::FULL_ACCESS)->value('id');
        if ($fullAccessId) {
            DB::table('admin_permission_user')->updateOrInsert(
                ['user_id' => $user->id, 'permission_id' => $fullAccessId],
                ['created_at' => now()],
            );
            $permissions->forget($user);
        }

        // Réinitialiser le compteur de tentatives échouées
        $dummyRequest = request();
        $throttle->clear($dummyRequest, $identifier);
        if ($user->email) {
            $throttle->clear($dummyRequest, $user->email);
        }
        if ($user->phone) {
            $throttle->clear($dummyRequest, $user->phone);
        }

        $this->newLine();
        $this->info("✓ Mot de passe mis à jour avec succès pour l'administrateur :");
        $this->line("  - Nom      : {$user->name}");
        $this->line("  - Email    : ".($user->email ?? 'Non renseigné'));
        $this->line("  - Téléphone: {$user->phone}");
        $this->line("  - Mot de passe : {$password}");
        if ($reset2fa) {
            $this->line("  - 2FA      : Réinitialisé (un nouveau QR code sera proposé)");
        } else {
            $this->line("  - 2FA      : ".($user->google_2fa_secret ? 'Actif' : 'Non configuré (QR code à la prochaine connexion)'));
        }

        return self::SUCCESS;
    }
}
