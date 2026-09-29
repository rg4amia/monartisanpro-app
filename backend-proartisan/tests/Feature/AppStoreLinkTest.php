<?php

namespace Tests\Feature;

use App\Models\AdminActivityLog;
use App\Models\AppStoreLink;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Chantier 16 — liens Google Play et App Store gérés depuis le backoffice et
 * affichés sur le site vitrine une fois validés.
 */
class AppStoreLinkTest extends TestCase
{
    use RefreshDatabase;

    private const PLAY_URL = 'https://play.google.com/store/apps/details?id=com.prosartisan.app';

    private const APPLE_URL = 'https://apps.apple.com/ci/app/prosartisan/id6450000001';

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'kyc_status' => 'actif']);
    }

    private function createLink(User $admin, string $platform, string $url): AppStoreLink
    {
        $this->actingAs($admin)
            ->post('/admin/applications-mobiles', ['platform' => $platform, 'url' => $url])
            ->assertSessionHasNoErrors();

        return AppStoreLink::query()->latest('id')->firstOrFail();
    }

    /** @return list<array<string, mixed>> */
    private function publicLinks(): array
    {
        return $this->getJson('/api/v1/vitrine/app-links')->assertOk()->json('data');
    }

    public function test_un_lien_cree_reste_invisible_jusqu_a_sa_validation(): void
    {
        $admin = $this->admin();
        $link = $this->createLink($admin, 'android', self::PLAY_URL);

        $this->assertSame(AppStoreLink::STATUS_DRAFT, $link->status);
        $this->assertSame([], $this->publicLinks());

        $this->actingAs($admin)->post("/admin/applications-mobiles/{$link->id}/valider")->assertSessionHasNoErrors();

        $this->assertSame([
            ['platform' => 'android', 'label' => 'Google Play', 'url' => self::PLAY_URL],
        ], $this->publicLinks());

        $this->actingAs($admin)->post("/admin/applications-mobiles/{$link->id}/desactiver")->assertSessionHasNoErrors();

        $this->assertSame(AppStoreLink::STATUS_DISABLED, $link->fresh()->status);
        $this->assertSame([], $this->publicLinks());
    }

    public function test_une_adresse_qui_ne_mene_pas_au_magasin_annonce_est_refusee(): void
    {
        $admin = $this->admin();

        foreach ([
            ['android', 'https://exemple.com/store/apps/details?id=com.prosartisan.app'],
            ['android', 'http://play.google.com/store/apps/details?id=com.prosartisan.app'],
            ['android', 'https://play.google.com/store/apps/details'],
            ['android', self::APPLE_URL],
            ['ios', self::PLAY_URL],
            ['ios', 'https://apps.apple.com.exemple.com/ci/app/prosartisan/id6450000001'],
            ['ios', 'javascript:alert(1)'],
        ] as [$platform, $url]) {
            $this->actingAs($admin)
                ->post('/admin/applications-mobiles', ['platform' => $platform, 'url' => $url])
                ->assertSessionHasErrors('url');
        }

        $this->actingAs($admin)
            ->post('/admin/applications-mobiles', ['platform' => 'windows', 'url' => self::PLAY_URL])
            ->assertSessionHasErrors('platform');

        $this->assertSame(0, AppStoreLink::count());
    }

    public function test_valider_un_lien_remplace_le_lien_publie_du_meme_magasin(): void
    {
        $admin = $this->admin();
        $first = $this->createLink($admin, 'android', self::PLAY_URL);
        $apple = $this->createLink($admin, 'ios', self::APPLE_URL);
        $this->actingAs($admin)->post("/admin/applications-mobiles/{$first->id}/valider");
        $this->actingAs($admin)->post("/admin/applications-mobiles/{$apple->id}/valider");

        $second = $this->createLink($admin, 'android', 'https://play.google.com/store/apps/details?id=com.prosartisan.client');
        $this->actingAs($admin)->post("/admin/applications-mobiles/{$second->id}/valider")->assertSessionHasNoErrors();

        $this->assertSame(AppStoreLink::STATUS_DISABLED, $first->fresh()->status);
        $this->assertSame(AppStoreLink::STATUS_PUBLISHED, $apple->fresh()->status);
        $this->assertSame([
            ['platform' => 'android', 'label' => 'Google Play', 'url' => 'https://play.google.com/store/apps/details?id=com.prosartisan.client'],
            ['platform' => 'ios', 'label' => 'App Store', 'url' => self::APPLE_URL],
        ], $this->publicLinks());

        $published = AdminActivityLog::where('action', 'app_store_link.published')->latest('id')->first();
        $this->assertSame(self::PLAY_URL, $published->context['remplace']);
    }

    public function test_un_lien_publie_ne_se_modifie_ni_ne_se_supprime(): void
    {
        $admin = $this->admin();
        $link = $this->createLink($admin, 'ios', self::APPLE_URL);
        $this->actingAs($admin)->post("/admin/applications-mobiles/{$link->id}/valider");

        $this->actingAs($admin)
            ->put("/admin/applications-mobiles/{$link->id}", ['url' => 'https://apps.apple.com/ci/app/autre/id1'])
            ->assertSessionHasErrors('url');
        $this->actingAs($admin)->delete("/admin/applications-mobiles/{$link->id}")->assertSessionHasErrors('link');

        $this->assertSame(self::APPLE_URL, $link->fresh()->url);

        // Désactivé, il redevient modifiable et supprimable.
        $this->actingAs($admin)->post("/admin/applications-mobiles/{$link->id}/desactiver");
        $this->actingAs($admin)
            ->put("/admin/applications-mobiles/{$link->id}", ['url' => 'https://apps.apple.com/app/prosartisan/id6450000002'])
            ->assertSessionHasNoErrors();
        $this->assertSame('https://apps.apple.com/app/prosartisan/id6450000002', $link->fresh()->url);

        $this->actingAs($admin)->delete("/admin/applications-mobiles/{$link->id}")->assertSessionHasNoErrors();
        $this->assertSame(0, AppStoreLink::count());
    }

    public function test_chaque_action_est_auditee(): void
    {
        $admin = $this->admin();
        $link = $this->createLink($admin, 'android', self::PLAY_URL);
        $this->actingAs($admin)->put("/admin/applications-mobiles/{$link->id}", ['url' => 'https://play.google.com/store/apps/details?id=ci.prosartisan.app']);
        $this->actingAs($admin)->post("/admin/applications-mobiles/{$link->id}/valider");
        $this->actingAs($admin)->post("/admin/applications-mobiles/{$link->id}/desactiver");
        $this->actingAs($admin)->delete("/admin/applications-mobiles/{$link->id}");

        $this->assertSame([
            'app_store_link.created',
            'app_store_link.updated',
            'app_store_link.published',
            'app_store_link.disabled',
            'app_store_link.deleted',
        ], AdminActivityLog::where('action', 'like', 'app_store_link.%')->orderBy('id')->pluck('action')->all());

        $updated = AdminActivityLog::where('action', 'app_store_link.updated')->first();
        $this->assertSame(self::PLAY_URL, $updated->context['avant']);
        $this->assertSame($admin->id, $updated->admin_id);
    }

    public function test_l_onglet_exige_la_capacite_vitrine(): void
    {
        $restricted = $this->admin();
        $permissionId = Permission::where('name', 'admin.faq.manage')->value('id');
        DB::table('admin_permission_user')->insert(['user_id' => $restricted->id, 'permission_id' => $permissionId, 'created_at' => now()]);

        $this->actingAs($restricted)->get('/admin/applications-mobiles')->assertForbidden();
        $this->actingAs($restricted)
            ->post('/admin/applications-mobiles', ['platform' => 'android', 'url' => self::PLAY_URL])
            ->assertForbidden();
        $this->assertSame(0, AppStoreLink::count());

        $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
        $this->actingAs($client)->get('/admin/applications-mobiles')->assertForbidden();

        $admin = $this->admin();
        $this->createLink($admin, 'ios', self::APPLE_URL);
        $this->actingAs($admin)
            ->get('/admin/applications-mobiles')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/app-store-links')
                ->where('appStoreLinks.0.platform_label', 'App Store')
                ->where('appStoreLinks.0.status_label', 'Brouillon')
                ->where('appStoreLinkOptions.platforms.android', 'Google Play'));
    }
}
