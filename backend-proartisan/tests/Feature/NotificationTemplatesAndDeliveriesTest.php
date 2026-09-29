<?php

namespace Tests\Feature;

use App\Jobs\DeliverNotificationJob;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Admin\AdminGdprService;
use App\Services\Admin\AdminObservabilityService;
use App\Services\Notifications\NotificationTemplateService;
use App\Services\NotificationService;
use App\Services\OneSignalService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Chantier 14, lot B — messages surchargeables, canaux par événement, envoi
 * après la réponse HTTP et journal des envois.
 */
class NotificationTemplatesAndDeliveriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.onesignal.app_id' => 'test-app-id',
            'services.onesignal.rest_api_key' => 'test-rest-key',
            'services.sms.provider' => 'log',
        ]);
    }

    private function fakeOneSignal(int $status = 200): void
    {
        Http::fake([
            'onesignal.com/*' => Http::response($status === 200 ? ['id' => 'x'] : ['errors' => ['Clé invalide']], $status),
        ]);
    }

    private function user(string $role = 'artisan', array $attributes = []): User
    {
        return User::factory()->create(['role' => $role, 'kyc_status' => 'actif', ...$attributes]);
    }

    private function notifications(): NotificationService
    {
        return app(NotificationService::class);
    }

    /**
     * @return array<string, string>
     */
    private function deliveriesFor(User $user): array
    {
        return NotificationDelivery::where('user_id', $user->id)
            ->get()
            ->mapWithKeys(fn (NotificationDelivery $d) => [$d->channel => $d->status])
            ->all();
    }

    public function test_le_texte_d_origine_est_repris_mot_pour_mot(): void
    {
        $this->fakeOneSignal();
        $artisan = $this->user();

        $this->notifications()->notify($artisan, 'jalon.paye.artisan', ['etape' => 2], ['mission_id' => 5]);

        $notification = Notification::where('user_id', $artisan->id)->sole();
        $this->assertSame('payment', $notification->type);
        $this->assertSame('jalon.paye.artisan', $notification->event_key);
        $this->assertSame('Paiement reçu !', $notification->title);
        $this->assertSame('Le jalon #2 a été validé. Paiement en cours.', $notification->body);
        $this->assertSame(['mission_id' => 5], $notification->data_json);
    }

    public function test_un_paiement_recu_part_aussi_par_sms_mais_pas_une_information_courante(): void
    {
        $this->fakeOneSignal();
        $artisan = $this->user();
        $client = $this->user('client');

        $this->notifications()->notify($artisan, 'jalon.paye.artisan', ['etape' => 1]);
        $this->notifications()->notify($client, 'mission.demande_acceptee.client');

        $this->assertSame(['push' => 'envoye', 'sms' => 'envoye'], $this->deliveriesFor($artisan));
        $this->assertSame(['push' => 'envoye'], $this->deliveriesFor($client));
        $this->assertSame('log', NotificationDelivery::where('channel', 'sms')->sole()->provider);
    }

    public function test_les_administrateurs_ne_recoivent_de_sms_que_pour_la_fraude_et_les_courses_bloquees(): void
    {
        $this->fakeOneSignal();
        $admins = collect([$this->user('admin'), $this->user('admin')]);

        $this->notifications()->notifyAdmins('litige.ouvert.admin', ['litige' => 3, 'mission' => 4]);
        $this->assertSame(0, NotificationDelivery::where('channel', 'sms')->count());

        $this->notifications()->notifyAdmins('jcode.fraude_gps.admin', ['code' => 'PA-1234', 'distance' => 250, 'max' => 100]);
        $this->assertSame(2, NotificationDelivery::where('channel', 'sms')->where('status', 'envoye')->count());

        $admins->each(fn (User $admin) => $this->assertSame(2, Notification::where('user_id', $admin->id)->count()));
    }

    public function test_un_texte_personnalise_remplace_le_texte_d_origine(): void
    {
        $this->fakeOneSignal();
        $artisan = $this->user();
        NotificationTemplate::create([
            'event_key' => 'jalon.paye.artisan',
            'push_title' => 'Étape {etape} payée',
            'push_body' => 'Bonne nouvelle : l\'étape {etape} vous est payée.',
        ]);

        $this->notifications()->notify($artisan, 'jalon.paye.artisan', ['etape' => 3]);

        $notification = Notification::where('user_id', $artisan->id)->sole();
        $this->assertSame('Étape 3 payée', $notification->title);
        $this->assertSame('Bonne nouvelle : l\'étape 3 vous est payée.', $notification->body);
    }

    public function test_un_texte_personnalise_inutilisable_retombe_sur_le_texte_d_origine(): void
    {
        $this->fakeOneSignal();
        $artisan = $this->user();
        // {montant} n'est pas fourni par l'appelant de cet événement.
        NotificationTemplate::create([
            'event_key' => 'jalon.paye.artisan',
            'push_body' => 'Vous recevez {montant} FCFA.',
        ]);

        $this->notifications()->notify($artisan, 'jalon.paye.artisan', ['etape' => 3]);

        $this->assertSame('Le jalon #3 a été validé. Paiement en cours.', Notification::where('user_id', $artisan->id)->sole()->body);
    }

    public function test_les_canaux_se_desactivent_evenement_par_evenement(): void
    {
        $this->fakeOneSignal();
        $artisan = $this->user();
        NotificationTemplate::create(['event_key' => 'jalon.paye.artisan', 'channel_sms' => false, 'channel_in_app' => false]);

        $this->notifications()->notify($artisan, 'jalon.paye.artisan', ['etape' => 1]);

        $this->assertSame(0, Notification::where('user_id', $artisan->id)->count());
        $this->assertSame(['push' => 'envoye'], $this->deliversForNullNotification($artisan));
    }

    public function test_push_et_sms_partent_apres_la_reponse_http(): void
    {
        Bus::fake();
        $artisan = $this->user();

        $this->notifications()->notify($artisan, 'jalon.paye.artisan', ['etape' => 1]);

        // La notification in-app est immédiate, l'envoi externe différé.
        $this->assertSame(1, Notification::where('user_id', $artisan->id)->count());
        Bus::assertDispatchedAfterResponse(
            DeliverNotificationJob::class,
            fn (DeliverNotificationJob $job) => $job->event === 'jalon.paye.artisan'
                && $job->push['data']['type'] === 'payment'
                && $job->sms === 'Paiement reçu !: Le jalon #1 a été validé. Paiement en cours.'
        );
    }

    public function test_une_notification_annulee_avant_l_envoi_n_est_pas_poussee(): void
    {
        $this->fakeOneSignal();
        $artisan = $this->user();

        (new DeliverNotificationJob($artisan->id, 999999, 'jalon.paye.artisan', [
            'title' => 'Titre', 'body' => 'Corps', 'data' => [], 'sound' => null,
        ], 'SMS'))->handle(app(OneSignalService::class), app(SmsService::class));

        Http::assertNothingSent();
        $this->assertSame(0, NotificationDelivery::count());
    }

    public function test_un_echec_onesignal_est_journalise_et_remonte_a_l_observabilite(): void
    {
        $this->fakeOneSignal(500);
        $client = $this->user('client');

        $this->notifications()->notify($client, 'mission.demande_acceptee.client');

        $delivery = NotificationDelivery::sole();
        $this->assertSame('echoue', $delivery->status);
        $this->assertStringContainsString('HTTP 500', $delivery->reason);

        $observability = app(AdminObservabilityService::class);
        $this->assertSame(1, $observability->criticalCounts()['failed_notifications_24h']);
        $snapshot = $observability->snapshot()['notifications'];
        $this->assertSame(1, $snapshot['failed_24h']);
        $this->assertSame('Demande de devis acceptée par l\'artisan', $snapshot['recent'][0]['event']);
    }

    public function test_onesignal_non_configure_et_numero_absent_sont_ignores_sans_panne(): void
    {
        config(['services.onesignal.app_id' => '', 'services.onesignal.rest_api_key' => '']);
        $artisan = $this->user('artisan', ['phone' => '']);

        $this->notifications()->notify($artisan, 'jalon.paye.artisan', ['etape' => 1]);

        $this->assertSame(['push' => 'ignore', 'sms' => 'ignore'], $this->deliveriesFor($artisan));
        $this->assertSame(0, app(AdminObservabilityService::class)->criticalCounts()['failed_notifications_24h']);
    }

    public function test_l_anti_doublon_kyc_reconnait_la_cle_et_les_anciennes_notifications(): void
    {
        $this->fakeOneSignal();
        $legacy = $this->user('client');
        $current = $this->user('client');
        $fresh = $this->user('client');
        $events = ['kyc.documents_attendus.utilisateur', 'kyc.en_examen.utilisateur'];

        // Notification antérieure au catalogue : pas de clé, titre d'origine.
        Notification::forceCreate(['user_id' => $legacy->id, 'type' => 'kyc', 'title' => 'Compte en attente de validation', 'body' => '…']);
        $this->notifications()->notify($current, 'kyc.en_examen.utilisateur');

        $this->assertTrue($this->notifications()->alreadyNotified($legacy, $events));
        $this->assertTrue($this->notifications()->alreadyNotified($current, $events));
        $this->assertFalse($this->notifications()->alreadyNotified($fresh, $events));
    }

    public function test_la_validation_refuse_variables_inconnues_obligatoires_absentes_et_sms_trop_long(): void
    {
        $templates = app(NotificationTemplateService::class);

        $errors = $templates->validate('commande.prete_retrait.client', [
            'push_body' => 'Votre commande {commande} est prête pour {client}.',
            'sms_body' => str_repeat('Texte trop long pour un SMS. ', 30).'{code}',
            'channel_sms' => true,
        ]);

        $this->assertContains('La variable {client} n\'existe pas pour ce message.', $errors['push_body']);
        $this->assertContains('La variable {code} est obligatoire dans ce message.', $errors['push_body']);
        $this->assertContains('Le SMS dépasse 3 segments : raccourcissez-le.', $errors['sms_body']);

        $this->assertSame([], $templates->validate('commande.prete_retrait.client', [
            'push_body' => 'Commande {commande} prête. Code : {code}.',
        ]));
    }

    public function test_le_decompte_sms_distingue_gsm_et_unicode(): void
    {
        $templates = app(NotificationTemplateService::class);

        $this->assertSame(['encoding' => 'gsm', 'length' => 160, 'segments' => 1], $templates->smsSegments(str_repeat('a', 160)));
        $this->assertSame(['encoding' => 'gsm', 'length' => 161, 'segments' => 2], $templates->smsSegments(str_repeat('a', 161)));
        // « à » et « é » sont dans l'alphabet GSM, « ç » et « ê » non.
        $this->assertSame('gsm', $templates->smsSegments('Paiement valide à Abidjan, étape 2')['encoding']);
        $this->assertSame('unicode', $templates->smsSegments('Paiement reçu')['encoding']);
        $this->assertSame(['encoding' => 'unicode', 'length' => 71, 'segments' => 2], $templates->smsSegments('ê'.str_repeat('a', 70)));
    }

    public function test_l_anonymisation_rgpd_efface_le_journal_des_envois(): void
    {
        $this->fakeOneSignal();
        $admin = $this->user('admin');
        $artisan = $this->user();
        $this->notifications()->notify($artisan, 'jalon.paye.artisan', ['etape' => 1]);
        $this->assertSame(2, NotificationDelivery::where('user_id', $artisan->id)->count());

        app(AdminGdprService::class)->anonymize($artisan, $admin);

        $this->assertSame(0, NotificationDelivery::where('user_id', $artisan->id)->count());
    }

    /**
     * @return array<string, string>
     */
    private function deliversForNullNotification(User $user): array
    {
        return NotificationDelivery::where('user_id', $user->id)
            ->whereNull('notification_id')
            ->get()
            ->mapWithKeys(fn (NotificationDelivery $d) => [$d->channel => $d->status])
            ->all();
    }
}
