<?php

use App\Models\User;
use App\Models\Vitrine\VitrineSlide;
use App\Models\Vitrine\VitrineArtisanDuMois;
use App\Models\Vitrine\VitrineArticle;
use App\Models\Vitrine\VitrineVideo;
use App\Models\Vitrine\VitrineFormation;
use App\Models\Vitrine\VitrineRecrutement;
use App\Models\Vitrine\VitrinePopup;
use App\Models\Vitrine\VitrineSetting;
use Database\Seeders\SectorSeeder;
use Database\Seeders\CommuneSeeder;

beforeEach(function () {
    // Seed communes and sectors/trades
    $this->seed(CommuneSeeder::class);
    $this->seed(SectorSeeder::class);
});

test('can retrieve public slides', function () {
    VitrineSlide::create([
        'titre' => 'Slide Test',
        'sous_titre' => 'Sous-titre',
        'image_url' => 'http://example.com/img.jpg',
        'actif' => true,
        'ordre' => 1
    ]);

    $response = $this->getJson('/api/v1/vitrine/slides');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['titre' => 'Slide Test']);
});

test('can retrieve artisan of the month', function () {
    $artisan = User::create([
        'phone' => '+2250211111111',
        'name' => 'Artisan Test',
        'password' => bcrypt('password'),
        'role' => 'artisan',
        'kyc_status' => 'actif'
    ]);

    VitrineArtisanDuMois::create([
        'user_id' => $artisan->id,
        'mois' => now()->startOfMonth()->toDateString(),
        'photo_override_url' => 'http://example.com/override.jpg',
        'texte_editorial' => 'Super artisan',
        'actif' => true
    ]);

    $response = $this->getJson('/api/v1/vitrine/artisan-du-mois');

    $response->assertStatus(200)
        ->assertJsonFragment(['texte_editorial' => 'Super artisan']);
});

test('can retrieve public articles', function () {
    VitrineArticle::create([
        'titre' => 'Article Test',
        'slug' => 'article-test',
        'contenu' => 'Contenu de test',
        'publie' => true,
        'publie_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/vitrine/articles');

    $response->assertStatus(200)
        ->assertJsonFragment(['titre' => 'Article Test']);
});

test('can retrieve specific article by slug', function () {
    VitrineArticle::create([
        'titre' => 'Article Test Slug',
        'slug' => 'article-test-slug',
        'contenu' => 'Contenu de test',
        'publie' => true,
        'publie_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/vitrine/articles/article-test-slug');

    $response->assertStatus(200)
        ->assertJsonFragment(['titre' => 'Article Test Slug']);
});

test('can retrieve public videos', function () {
    VitrineVideo::create([
        'titre' => 'Video Test',
        'video_url' => 'http://youtube.com/test',
        'actif' => true,
    ]);

    $response = $this->getJson('/api/v1/vitrine/videos');

    $response->assertStatus(200)
        ->assertJsonFragment(['titre' => 'Video Test']);
});

test('can retrieve public formations', function () {
    VitrineFormation::create([
        'titre' => 'Formation Test',
        'description' => 'Description de test',
        'date_debut' => now()->addDays(5)->toDateString(),
        'lieu' => 'Abidjan',
        'actif' => true,
    ]);

    $responseList = $this->getJson('/api/v1/vitrine/formations');
    $responseList->assertStatus(200)->assertJsonFragment(['titre' => 'Formation Test']);
});

test('can retrieve public recrutements and exclude expired offers', function () {
    // 1. Offre active valide
    VitrineRecrutement::create([
        'titre' => 'Offre Active Valide',
        'description' => 'Description de test',
        'metier' => 'Plombier',
        'lieu' => 'Cocody',
        'type_contrat' => 'cdi',
        'date_limite' => now()->addDays(10)->toDateString(),
        'actif' => true,
    ]);

    // 2. Offre expirée (date limite dépassée)
    VitrineRecrutement::create([
        'titre' => 'Offre Expiree Depassee',
        'description' => 'Cette offre ne doit pas apparaitre',
        'metier' => 'Électricien',
        'lieu' => 'Yopougon',
        'type_contrat' => 'cdd',
        'date_limite' => now()->subDay()->toDateString(),
        'actif' => true,
    ]);

    $responseList = $this->getJson('/api/v1/vitrine/recrutements');
    $responseList->assertStatus(200)
        ->assertJsonFragment(['titre' => 'Offre Active Valide'])
        ->assertJsonMissing(['titre' => 'Offre Expiree Depassee']);
});

test('can retrieve active popup', function () {
    VitrinePopup::create([
        'titre' => 'Popup Test',
        'date_debut' => now()->subDay(),
        'date_fin' => now()->addDay(),
        'actif' => true,
    ]);

    $response = $this->getJson('/api/v1/vitrine/popup');

    $response->assertStatus(200)
        ->assertJsonFragment(['titre' => 'Popup Test']);
});

test('can retrieve public vitrine settings', function () {
    VitrineSetting::create([
        'cle' => 'vitrine_test_key',
        'valeur' => 'valeur_test'
    ]);

    $response = $this->getJson('/api/v1/vitrine/settings');

    $response->assertStatus(200)
        ->assertJsonFragment(['vitrine_test_key' => 'valeur_test']);
});

test('can list highly rated artisans and search artisans', function () {
    $artisan = User::create([
        'phone' => '+2250299999999',
        'name' => 'Top Artisan',
        'password' => bcrypt('password'),
        'role' => 'artisan',
        'kyc_status' => 'actif',
        'score_prosartisan' => 950
    ]);

    $responseTop = $this->getJson('/api/v1/vitrine/artisans-stars');
    $responseTop->assertStatus(200)->assertJsonFragment(['name' => 'Top Artisan']);

    $responseSearch = $this->getJson('/api/v1/vitrine/artisans?q=Top');
    $responseSearch->assertStatus(200);
});

test('can submit public contact form and persist in database', function () {
    $artisan = User::create([
        'phone' => '+2250788888888',
        'name' => 'Artisan Ciblé',
        'role' => 'artisan',
        'kyc_status' => 'actif',
        'score_prosartisan' => 900,
        'wallet_materiaux' => 0,
        'wallet_mo' => 0,
        'password' => bcrypt('password'),
    ]);

    $payload = [
        'nom' => 'Kouassi Jean',
        'email' => 'jean.kouassi@gmail.com',
        'telephone' => '+2250701020304',
        'sujet' => 'Devis rénovation électrique',
        'message' => 'Bonjour, je souhaite un devis complet pour refaire l\'installation.',
        'artisan_id' => $artisan->id,
    ];

    $response = $this->postJson('/api/v1/vitrine/contact', $payload);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    $this->assertDatabaseHas('contact_messages', [
        'nom' => 'Kouassi Jean',
        'email' => 'jean.kouassi@gmail.com',
        'telephone' => '+2250701020304',
        'sujet' => 'Devis rénovation électrique',
        'artisan_id' => $artisan->id,
        'statut' => 'nouveau',
    ]);
});
