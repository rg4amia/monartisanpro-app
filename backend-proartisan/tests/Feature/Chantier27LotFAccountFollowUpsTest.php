<?php

namespace Tests\Feature;

use App\Jobs\DeliverNotificationJob;
use App\Models\Notification;
use App\Models\Otp;
use App\Models\Permission;
use App\Models\User;
use App\Services\AccountPhoneService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Chantier 27, lot F — constats restés ouverts de l'analyse « Utilisateurs » :
 * changement de numéro par le titulaire (sessions, ancien numéro prévenu) et
 * filtre de la liste sur le statut du compte.
 */
class Chantier27LotFAccountFollowUpsTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_PHONE = '+2250700000001';

    private const NEW_PHONE = '+2250500000002';

    private function account(array $attributes = []): User
    {
        return User::factory()->create([
            'role' => 'artisan', 'kyc_status' => 'actif', 'phone' => self::OLD_PHONE, ...$attributes,
        ]);
    }

    private function code(string $phone, ?string $action = AccountPhoneService::OTP_ACTION): string
    {
        Otp::create(['phone' => $phone, 'code' => '4821', 'action' => $action, 'expires_at' => now()->addMinutes(5)]);

        return '4821';
    }

    // ─── Changement de numéro par le titulaire ─────────────────────────────

    public function test_le_changement_de_numero_ferme_les_autres_sessions_et_garde_celle_en_cours(): void
    {
        $user = $this->account();
        $current = $user->createToken('telephone-en-main');
        $other = $user->createToken('telephone-perdu');

        $this->withToken($current->plainTextToken)
            ->postJson('/api/v1/auth/change-phone', ['new_phone' => self::NEW_PHONE, 'otp' => $this->code(self::NEW_PHONE)])
            ->assertOk()
            ->assertJsonPath('user.phone', self::NEW_PHONE);

        $this->assertSame(self::NEW_PHONE, $user->fresh()->phone);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
    }

    public function test_l_ancien_numero_est_prevenu_par_sms(): void
    {
        Bus::fake([DeliverNotificationJob::class]);
        $user = $this->account();

        $this->withToken($user->createToken('app')->plainTextToken)
            ->postJson('/api/v1/auth/change-phone', ['new_phone' => self::NEW_PHONE, 'otp' => $this->code(self::NEW_PHONE)])
            ->assertOk();

        $notification = Notification::where('user_id', $user->id)->sole();
        $this->assertSame('compte.telephone_change.utilisateur', $notification->event_key);
        $this->assertStringContainsString(self::NEW_PHONE, $notification->body);

        Bus::assertDispatchedAfterResponse(
            DeliverNotificationJob::class,
            fn (DeliverNotificationJob $job) => $job->smsPhone === self::OLD_PHONE && $job->sms !== null
        );
    }

    public function test_un_code_de_connexion_ne_vaut_pas_pour_un_changement_de_numero(): void
    {
        $user = $this->account();

        $this->withToken($user->createToken('app')->plainTextToken)
            ->postJson('/api/v1/auth/change-phone', ['new_phone' => self::NEW_PHONE, 'otp' => $this->code(self::NEW_PHONE, null)])
            ->assertStatus(422)
            ->assertJsonPath('message', AccountPhoneService::INVALID_CODE_MESSAGE);

        $this->assertSame(self::OLD_PHONE, $user->fresh()->phone);
    }

    public function test_un_numero_deja_pris_est_refuse_avant_tout_envoi_de_code(): void
    {
        $user = $this->account();
        // Même supprimé, un compte garde son numéro : la colonne est unique.
        User::factory()->create(['phone' => self::NEW_PHONE])->delete();

        $this->withToken($user->createToken('app')->plainTextToken)
            ->postJson('/api/v1/auth/change-phone', ['new_phone' => self::NEW_PHONE])
            ->assertStatus(422)
            ->assertJsonPath('errors.new_phone.0', AccountPhoneService::TAKEN_MESSAGE);

        $this->assertSame(0, Otp::where('phone', self::NEW_PHONE)->count());
    }

    // ─── Filtre sur le statut du compte ────────────────────────────────────

    public function test_la_liste_se_filtre_sur_le_statut_du_compte(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif', 'name' => 'Admin']);
        DB::table('admin_permission_user')->insert([
            'user_id' => $admin->id,
            'permission_id' => Permission::where('name', 'admin.full-access')->value('id'),
            'created_at' => now(),
        ]);

        User::factory()->create(['name' => 'Compte suspendu', 'account_status' => 'suspendu']);
        User::factory()->create(['name' => 'Compte banni', 'account_status' => 'banni']);
        User::factory()->create(['name' => 'Compte actif', 'account_status' => 'actif']);
        User::factory()->create(['name' => 'Compte anonymisé', 'account_status' => 'suspendu', 'anonymized_at' => now()]);

        $names = function (string $state) use ($admin): array {
            $found = [];
            $this->actingAs($admin)->get('/admin/users?etat_users='.$state)
                ->assertInertia(function (AssertableInertia $page) use (&$found) {
                    $found = collect($page->toArray()['props']['usersPage']['data'])->pluck('name')->sort()->values()->all();
                });

            return $found;
        };

        $this->assertSame(['Compte suspendu'], $names('suspendu'));
        $this->assertSame(['Compte banni'], $names('banni'));
        $this->assertSame(['Compte anonymisé'], $names('anonymises'));
        $this->assertSame(['Admin', 'Compte actif'], $names('actif'));
    }
}
