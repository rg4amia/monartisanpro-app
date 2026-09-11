<?php

namespace Tests\Unit;

use App\Models\Mission;
use App\Services\AntiCircumventionService;
use App\States\Mission\DraftState;
use App\States\Mission\InProgressState;
use Tests\TestCase;

class AntiCircumventionServiceTest extends TestCase
{
    private AntiCircumventionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AntiCircumventionService();
    }

    public function test_it_redacts_phone_numbers_before_funding(): void
    {
        $mission = new Mission();
        $mission->status = new DraftState($mission);

        $content = "Bonjour, appelle-moi au 07 01 02 03 04 pour caler le passage.";
        $result = $this->service->inspectAndFilter($content, $mission);

        $this->assertTrue($result['is_redacted']);
        $this->assertTrue($result['flagged']);
        $this->assertStringContainsString('[COORDONNÉES MASQUÉES AVANT VALIDATION DU DEVIS]', $result['content']);
        $this->assertStringNotContainsString('07 01 02 03 04', $result['content']);
    }

    public function test_it_redacts_circumvention_keywords_before_funding(): void
    {
        $mission = new Mission();
        $mission->status = new DraftState($mission);

        $content = "On peut faire ça en direct sans problème.";
        $result = $this->service->inspectAndFilter($content, $mission);

        $this->assertTrue($result['is_redacted']);
        $this->assertTrue($result['flagged']);
        $this->assertStringContainsString('[MESSAGE SIGNALÉ : ÉCHANGE HORS PLATEFORME INTERDIT]', $result['content']);
    }

    public function test_it_allows_numbers_and_text_freely_after_funding(): void
    {
        $mission = new Mission();
        $mission->status = new InProgressState($mission);

        $content = "Je suis au portail, contactez-moi au 0701020304 si besoin.";
        $result = $this->service->inspectAndFilter($content, $mission);

        $this->assertFalse($result['is_redacted']);
        $this->assertStringContainsString('0701020304', $result['content']);
    }

    public function test_it_does_not_flag_standard_construction_terms(): void
    {
        $mission = new Mission();
        $mission->status = new DraftState($mission);

        $content = "Pouvez-vous me confirmer le diamètre des tuyaux en PVC et la couleur de la peinture ?";
        $result = $this->service->inspectAndFilter($content, $mission);

        $this->assertFalse($result['is_redacted']);
        $this->assertFalse($result['flagged']);
        $this->assertEquals($content, $result['content']);
    }
}
