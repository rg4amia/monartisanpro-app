<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vitrine\VitrineArticle;
use App\Models\Vitrine\VitrineArtisanDuMois;
use App\Models\Vitrine\VitrineFormation;
use App\Models\Vitrine\VitrinePopup;
use App\Models\Vitrine\VitrineRecrutement;
use App\Models\Vitrine\VitrineSlide;
use App\Models\Vitrine\VitrineVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VitrineAdminFullCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'phone' => '0700000099',
            'role' => 'admin',
            'kyc_status' => 'actif',
        ]);

        Storage::fake('public');
    }

    public function test_can_crud_hero_slides(): void
    {
        $file = UploadedFile::fake()->image('slide.jpg');

        $response = $this->actingAs($this->admin)->post('/admin/vitrine/slides', [
            'titre' => 'Nouveau Slide Hero',
            'sous_titre' => 'Sous titre test',
            'image' => $file,
            'cta_texte' => 'Découvrir',
            'cta_lien' => '/artisans',
            'ordre' => 1,
            'actif' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vitrine_slides', ['titre' => 'Nouveau Slide Hero']);

        $slide = VitrineSlide::first();
        $this->actingAs($this->admin)->put("/admin/vitrine/slides/{$slide->id}", [
            'titre' => 'Slide Modifié',
            'ordre' => 2,
            'actif' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('vitrine_slides', ['titre' => 'Slide Modifié']);

        $this->actingAs($this->admin)->delete("/admin/vitrine/slides/{$slide->id}")->assertRedirect();
        $this->assertDatabaseMissing('vitrine_slides', ['id' => $slide->id]);
    }

    public function test_can_crud_artisan_du_mois(): void
    {
        $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);
        $file = UploadedFile::fake()->image('artisan.jpg');

        $response = $this->actingAs($this->admin)->post('/admin/vitrine/artisan-du-mois', [
            'user_id' => $artisan->id,
            'mois' => '2026-08',
            'photo' => $file,
            'texte_editorial' => 'Artisan d\'exception',
            'actif' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vitrine_artisan_du_mois', ['user_id' => $artisan->id]);

        $adm = VitrineArtisanDuMois::first();
        $this->actingAs($this->admin)->delete("/admin/vitrine/artisan-du-mois/{$adm->id}")->assertRedirect();
        $this->assertDatabaseMissing('vitrine_artisan_du_mois', ['id' => $adm->id]);
    }

    public function test_can_crud_articles(): void
    {
        $file = UploadedFile::fake()->image('article.jpg');

        $response = $this->actingAs($this->admin)->post('/admin/vitrine/articles', [
            'titre' => 'Lancement Partenariat Quincailleries',
            'contenu' => 'Contenu détaillé de l\'article',
            'image' => $file,
            'categorie' => 'partenariat',
            'publie' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vitrine_articles', ['titre' => 'Lancement Partenariat Quincailleries']);

        $article = VitrineArticle::first();
        $this->actingAs($this->admin)->put("/admin/vitrine/articles/{$article->id}", [
            'titre' => 'Titre Modifié',
            'contenu' => 'Nouveau contenu',
            'categorie' => 'actualite',
            'publie' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('vitrine_articles', ['titre' => 'Titre Modifié', 'publie' => false]);

        $this->actingAs($this->admin)->delete("/admin/vitrine/articles/{$article->id}")->assertRedirect();
        $this->assertDatabaseMissing('vitrine_articles', ['id' => $article->id]);
    }

    public function test_can_crud_videos(): void
    {
        $file = UploadedFile::fake()->image('video_thumb.jpg');

        $response = $this->actingAs($this->admin)->post('/admin/vitrine/videos', [
            'titre' => 'Tutoriel J-Code',
            'description' => 'Comment scanner un jcode',
            'video_url' => 'https://www.youtube.com/watch?v=test',
            'thumbnail' => $file,
            'categorie' => 'capsule',
            'ordre' => 1,
            'actif' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vitrine_videos', ['titre' => 'Tutoriel J-Code']);

        $video = VitrineVideo::first();
        $this->actingAs($this->admin)->put("/admin/vitrine/videos/{$video->id}", [
            'titre' => 'Tutoriel J-Code v2',
            'video_url' => 'https://www.youtube.com/watch?v=test2',
            'categorie' => 'formation',
            'ordre' => 2,
            'actif' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('vitrine_videos', ['titre' => 'Tutoriel J-Code v2']);

        $this->actingAs($this->admin)->delete("/admin/vitrine/videos/{$video->id}")->assertRedirect();
        $this->assertDatabaseMissing('vitrine_videos', ['id' => $video->id]);
    }

    public function test_can_crud_formations(): void
    {
        $file = UploadedFile::fake()->image('formation.jpg');

        $response = $this->actingAs($this->admin)->post('/admin/vitrine/formations', [
            'titre' => 'Formation Électriciens 2026',
            'description' => 'Mise aux normes',
            'image' => $file,
            'date_debut' => '2026-09-10',
            'lieu' => 'Abidjan',
            'formateur' => 'Ingénieur Alpha',
            'places_total' => 25,
            'tarif' => 15000,
            'actif' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vitrine_formations', ['titre' => 'Formation Électriciens 2026']);

        $formation = VitrineFormation::first();
        $this->actingAs($this->admin)->put("/admin/vitrine/formations/{$formation->id}", [
            'titre' => 'Formation Électriciens Avancée',
            'description' => 'Description modifiée',
            'date_debut' => '2026-09-15',
            'lieu' => 'Cocody',
            'tarif' => 20000,
            'actif' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('vitrine_formations', ['titre' => 'Formation Électriciens Avancée']);

        $this->actingAs($this->admin)->delete("/admin/vitrine/formations/{$formation->id}")->assertRedirect();
        $this->assertDatabaseMissing('vitrine_formations', ['id' => $formation->id]);
    }

    public function test_can_crud_recrutements(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/vitrine/recrutements', [
            'titre' => 'Recrutement 10 Plombiers',
            'description' => 'Projet résidentiel',
            'metier' => 'Plombier',
            'lieu' => 'Yopougon',
            'type_contrat' => 'freelance',
            'date_limite' => '2026-10-01',
            'actif' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vitrine_recrutements', ['titre' => 'Recrutement 10 Plombiers']);

        $recrutement = VitrineRecrutement::first();
        $this->actingAs($this->admin)->put("/admin/vitrine/recrutements/{$recrutement->id}", [
            'titre' => 'Recrutement 20 Plombiers',
            'description' => 'Projet résidentiel étendu',
            'metier' => 'Plombier',
            'lieu' => 'Abidjan',
            'type_contrat' => 'cdd',
            'actif' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('vitrine_recrutements', ['titre' => 'Recrutement 20 Plombiers']);

        $this->actingAs($this->admin)->delete("/admin/vitrine/recrutements/{$recrutement->id}")->assertRedirect();
        $this->assertDatabaseMissing('vitrine_recrutements', ['id' => $recrutement->id]);
    }

    public function test_can_crud_popups(): void
    {
        $file = UploadedFile::fake()->image('popup.jpg');

        $response = $this->actingAs($this->admin)->post('/admin/vitrine/popups', [
            'titre' => 'Offre Spéciale Rentrée',
            'contenu' => 'Profitez de -10%',
            'image' => $file,
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-09-30',
            'actif' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vitrine_popups', ['titre' => 'Offre Spéciale Rentrée']);

        $popup = VitrinePopup::first();
        $this->actingAs($this->admin)->put("/admin/vitrine/popups/{$popup->id}", [
            'titre' => 'Offre Rentrée Prolongée',
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-10-15',
            'actif' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('vitrine_popups', ['titre' => 'Offre Rentrée Prolongée']);

        $this->actingAs($this->admin)->delete("/admin/vitrine/popups/{$popup->id}")->assertRedirect();
        $this->assertDatabaseMissing('vitrine_popups', ['id' => $popup->id]);
    }

    public function test_can_update_settings(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/vitrine/settings', [
            'chiffres_cles_artisans' => '500+',
            'chiffres_cles_utilisateurs' => '1200+',
            'chiffres_cles_missions' => '3500+',
            'chiffres_cles_metiers' => '25',
            'lien_facebook' => 'https://facebook.com/prosartisan',
            'lien_instagram' => 'https://instagram.com/prosartisan',
            'lien_linkedin' => 'https://linkedin.com/company/prosartisan',
            'contact_phone' => '+225 07 00 00 00 00',
            'contact_email' => 'contact@prosartisan.ci',
            'presentation_mission' => 'Sécuriser l\'artisanat ivoirien.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vitrine_settings', ['cle' => 'chiffres_cles_artisans', 'valeur' => '500+']);
    }
}
