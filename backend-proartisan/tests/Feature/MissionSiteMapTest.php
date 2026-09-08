<?php

/**
 * Tests Pest – FEATURE : carte du chantier de l'artisan.
 *
 * GET /api/v1/missions/{mission}/site-map renvoie :
 *   – la position du client (seulement si mission financée + demandeur = artisan
 *     affecté / client / admin) ;
 *   – la liste des fournisseurs chez qui des J-Codes ont été retirés.
 */

use App\Models\FournisseurAgree;
use App\Models\JCode;
use App\Models\Mission;
use App\Models\User;

function makeFundedMissionWithClientPosition(): array
{
    $client = User::factory()->create(['role' => 'client', 'kyc_status' => 'actif']);
    $artisan = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

    $mission = Mission::create([
        'client_id' => $client->id,
        'artisan_id' => $artisan->id,
        'description' => 'Chantier test',
        'status' => 'in_progress',
        'montant_total' => 100000,
        'montant_materiaux' => 65000,
        'montant_mo' => 35000,
        'ratio_materiaux' => 0.65,
        'client_latitude' => 5.3599,
        'client_longitude' => -4.0083,
        'client_address' => 'Cocody, Abidjan',
    ]);

    return [$client, $artisan, $mission];
}

it('révèle la position du client à l\'artisan affecté sur mission financée', function () {
    [$client, $artisan, $mission] = makeFundedMissionWithClientPosition();

    $this->actingAs($artisan)
        ->getJson("/api/v1/missions/{$mission->id}/site-map")
        ->assertOk()
        ->assertJsonPath('data.client.coordinates.lat', 5.3599)
        ->assertJsonPath('data.client.coordinates.lng', -4.0083)
        ->assertJsonPath('data.suppliers', []);
});

it('liste les fournisseurs des J-Codes de la mission', function () {
    [$client, $artisan, $mission] = makeFundedMissionWithClientPosition();

    $fournisseur = User::factory()->create(['role' => 'fournisseur', 'kyc_status' => 'actif', 'name' => 'Quincaillerie Nord']);
    $agree = FournisseurAgree::create([
        'user_id' => $fournisseur->id,
        'nom_boutique' => 'Quincaillerie Nord',
        'statut' => 'agree',
    ]);
    $agree->setPosition(5.37, -4.01);

    JCode::create([
        'mission_id' => $mission->id,
        'artisan_id' => $artisan->id,
        'fournisseur_id' => $fournisseur->id,
        'code' => 'PA-TST1',
        'montant' => 40000,
        'statut' => 'utilise',
        'expires_at' => now()->addDay(),
    ]);

    $response = $this->actingAs($artisan)
        ->getJson("/api/v1/missions/{$mission->id}/site-map")
        ->assertOk()
        ->assertJsonPath('data.suppliers.0.name', 'Quincaillerie Nord')
        ->assertJsonPath('data.suppliers.0.jcodeCount', 1)
        ->assertJsonPath('data.suppliers.0.montant', 40000);

    expect($response->json('data.suppliers.0.coordinates.lat'))->toEqualWithDelta(5.37, 0.0001);
});

it('masque la position du client tant que la mission n\'est pas financée', function () {
    [, $artisan, $mission] = makeFundedMissionWithClientPosition();
    $mission->update(['status' => 'pending_funding']);

    $this->actingAs($artisan)
        ->getJson("/api/v1/missions/{$mission->id}/site-map")
        ->assertOk()
        ->assertJsonPath('data.client', null);
});

it('refuse un utilisateur tiers', function () {
    [$client, $artisan, $mission] = makeFundedMissionWithClientPosition();
    $intrus = User::factory()->create(['role' => 'artisan', 'kyc_status' => 'actif']);

    $this->actingAs($intrus)
        ->getJson("/api/v1/missions/{$mission->id}/site-map")
        ->assertForbidden();
});
