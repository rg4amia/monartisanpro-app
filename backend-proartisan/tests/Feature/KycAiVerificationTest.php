<?php

namespace Tests\Feature;

use App\Models\KycDocument;
use App\Models\User;
use App\Services\KycService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Vérification KYC par IA (OCR de la pièce + biométrie faciale Gemini).
 *
 * L'auto-approbation active un compte sans regard humain : elle doit échouer
 * fermée. La première version renvoyait, faute de clé Gemini, un faux résultat
 * « visages concordants à 94 % » qui activait n'importe quel dossier en
 * production ; elle réutilisait aussi une comparaison faciale établie avec une
 * CNI remplacée depuis.
 */
class KycAiVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $ocr;

    /** @var array<int, array<string, mixed>> Réponses successives de la comparaison faciale. */
    private array $faces;

    private int $faceCalls = 0;

    private int $geminiStatus;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->ocr = [
            'document_type' => 'cni',
            'document_number' => 'CI 0029-384.756',
            'last_name' => 'KOUASSI',
            'first_name' => 'Awa',
            'birth_date' => '1988-04-12',
            'expiry_date' => now()->addYears(3)->toDateString(),
            'is_expired' => false,
            'is_legible' => true,
            'has_photo' => true,
            'is_tampered' => false,
            'quality_score' => 93,
            'anomalies' => [],
            'summary' => 'Pièce lisible.',
        ];
        $this->faces = [$this->face()];
    }

    /**
     * @return array<string, mixed>
     */
    private function face(array $overrides = []): array
    {
        return array_merge([
            'face_matched' => true,
            'similarity_score' => 95,
            'liveness_detected' => true,
            'liveness_score' => 93,
            'overall_confidence_score' => 94,
            'anomalies' => [],
            'summary' => 'Même personne.',
        ], $overrides);
    }

    /**
     * Configure une clé Gemini et simule ses réponses : un appel à deux images
     * est une comparaison faciale, un appel à une image une lecture de pièce.
     */
    private function fakeGemini(int $status = 200): void
    {
        config(['services.gemini.api_key' => 'test-gemini-key']);

        // Les faux HTTP successifs se cumulent (le premier l'emporte) : on
        // n'en enregistre qu'un, dont le statut reste modifiable.
        $alreadyFaked = isset($this->geminiStatus);
        $this->geminiStatus = $status;
        if ($alreadyFaked) {
            return;
        }

        Http::fake(function (Request $request) {
            if ($this->geminiStatus !== 200) {
                return Http::response(['error' => 'indisponible'], $this->geminiStatus);
            }

            $images = collect($request->data()['contents'][0]['parts'] ?? [])
                ->filter(fn ($part) => isset($part['inline_data']))
                ->count();

            $payload = $images >= 2
                ? ($this->faces[min($this->faceCalls++, count($this->faces) - 1)])
                : $this->ocr;

            return Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($payload)]]]]],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
            ]);
        });
    }

    private function upload(User $user, string $type): KycDocument
    {
        return app(KycService::class)->uploadDocument($user, $type, UploadedFile::fake()->image("{$type}.jpg"));
    }

    private function applicant(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'artisan', 'kyc_status' => 'en_attente'], $attributes));
    }

    public function test_without_a_gemini_key_no_account_is_ever_activated(): void
    {
        // phpunit.xml : GEMINI_API_KEY=PLACEHOLDER_KEY, comme une production mal configurée.
        Http::fake();
        $user = $this->applicant();

        $this->upload($user, 'cni');
        $selfie = $this->upload($user, 'selfie');

        $this->assertSame('en_attente', $user->fresh()->kyc_status);
        $this->assertFalse($selfie->auto_verified);
        $this->assertFalse($selfie->face_matched);
        $this->assertFalse($selfie->ai_analysis['analysis_available']);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'generateContent'));
    }

    public function test_a_gemini_outage_leaves_the_file_in_human_review(): void
    {
        $this->fakeGemini(500);
        $user = $this->applicant();

        $cni = $this->upload($user, 'cni');
        $this->upload($user, 'selfie');

        $this->assertSame('en_attente', $user->fresh()->kyc_status);
        $this->assertNull($cni->ai_confidence_score);
        $this->assertContains('analyse_piece_indisponible', app(KycService::class)->autoApprovalBlockers($user->fresh()));
    }

    public function test_a_matching_identity_and_selfie_activate_the_account(): void
    {
        $this->fakeGemini();
        $user = $this->applicant();

        $cni = $this->upload($user, 'cni');
        $this->upload($user, 'selfie');

        $this->assertSame('actif', $user->fresh()->kyc_status);
        $this->assertSame('CI0029384756', $cni->fresh()->ocr_document_number);
        $this->assertSame(2, KycDocument::where('user_id', $user->id)
            ->where('statut', 'approuve')->where('auto_verified', true)->count());
    }

    public function test_the_selfie_may_be_uploaded_before_the_identity_document(): void
    {
        $this->fakeGemini();
        $user = $this->applicant();

        $this->upload($user, 'selfie');
        $this->assertSame('en_attente', $user->fresh()->kyc_status);

        $this->upload($user, 'cni');
        $this->assertSame('actif', $user->fresh()->kyc_status);
    }

    public function test_a_replaced_identity_document_is_compared_to_the_selfie_again(): void
    {
        // 1er dossier : visages concordants mais pièce illisible → pas d'activation.
        // Remplacement par la pièce d'un tiers : elle ne correspond pas au selfie.
        $this->ocr['is_legible'] = false;
        $this->faces = [$this->face(), $this->face(['face_matched' => false, 'similarity_score' => 20, 'overall_confidence_score' => 20])];
        $this->fakeGemini();
        $user = $this->applicant();

        $this->upload($user, 'cni');
        $this->upload($user, 'selfie');
        $this->assertSame('en_attente', $user->fresh()->kyc_status);

        $this->ocr['is_legible'] = true;
        $newCni = $this->upload($user, 'cni');

        $selfie = KycDocument::where('user_id', $user->id)->where('type', 'selfie')->first();
        $this->assertSame($newCni->id, $selfie->ai_analysis['cni_document_id']);
        $this->assertSame('en_attente', $user->fresh()->kyc_status);
    }

    public function test_a_selfie_failing_the_liveness_check_is_not_approved(): void
    {
        $this->faces = [$this->face(['liveness_detected' => false, 'liveness_score' => 30])];
        $this->fakeGemini();
        $user = $this->applicant();

        $this->upload($user, 'cni');
        $selfie = $this->upload($user, 'selfie');

        $this->assertSame('en_attente', $user->fresh()->kyc_status);
        // Le score global est plafonné par le contrôle de vivacité le plus faible.
        $this->assertSame(30, $selfie->ai_confidence_score);
    }

    public function test_an_expired_document_is_refused_even_if_the_model_misses_it(): void
    {
        $this->ocr['expiry_date'] = now()->subDay()->toDateString();
        $this->ocr['is_expired'] = false;
        $this->fakeGemini();
        $user = $this->applicant();

        $this->upload($user, 'cni');
        $this->upload($user, 'selfie');

        $this->assertSame('en_attente', $user->fresh()->kyc_status);
        $this->assertContains('piece_expiree', app(KycService::class)->autoApprovalBlockers($user->fresh()));
    }

    public function test_an_identity_document_already_used_by_another_account_is_not_approved(): void
    {
        $this->fakeGemini();
        $first = $this->applicant();
        $this->upload($first, 'cni');
        $this->upload($first, 'selfie');
        $this->assertSame('actif', $first->fresh()->kyc_status);

        $second = $this->applicant();
        $this->upload($second, 'cni');
        $this->upload($second, 'selfie');

        $this->assertSame('en_attente', $second->fresh()->kyc_status);
        $this->assertContains('piece_deja_utilisee_par_un_autre_compte', app(KycService::class)->autoApprovalBlockers($second->fresh()));
    }

    public function test_a_file_rejected_by_an_administrator_is_never_reactivated_by_the_ai(): void
    {
        $this->fakeGemini();
        $user = $this->applicant(['kyc_status' => 'rejete']);

        $this->upload($user, 'cni');
        $this->upload($user, 'selfie');

        $this->assertSame('rejete', $user->fresh()->kyc_status);
    }

    public function test_suppliers_stay_under_human_review(): void
    {
        $this->fakeGemini();
        $user = $this->applicant(['role' => 'fournisseur']);

        $this->upload($user, 'cni');
        $this->upload($user, 'selfie');

        $this->assertSame('en_attente', $user->fresh()->kyc_status);
    }

    public function test_auto_approval_can_be_switched_off(): void
    {
        config(['prosartisan.kyc.auto_approval_enabled' => false]);
        $this->fakeGemini();
        $user = $this->applicant();

        $this->upload($user, 'cni');
        $this->upload($user, 'selfie');

        $this->assertSame('en_attente', $user->fresh()->kyc_status);
    }

    public function test_the_upload_endpoint_reports_the_ai_result(): void
    {
        $this->fakeGemini();
        $user = $this->applicant();

        $this->actingAs($user)
            ->postJson('/api/v1/kyc/upload-cni', ['file' => UploadedFile::fake()->image('cni.jpg')])
            ->assertOk()
            ->assertJsonPath('data.kyc_status', 'en_attente')
            ->assertJsonPath('data.ocr_data.document_number', 'CI0029384756');

        $this->actingAs($user)
            ->postJson('/api/v1/kyc/upload-selfie', ['file' => UploadedFile::fake()->image('selfie.jpg')])
            ->assertOk()
            ->assertJsonPath('data.auto_verified', true)
            ->assertJsonPath('data.kyc_status', 'actif');
    }

    public function test_verify_endpoint_requires_both_documents(): void
    {
        $this->fakeGemini();
        $user = $this->applicant();

        $this->actingAs($user)
            ->postJson('/api/v1/kyc/verify-ai')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_verify_endpoint_reruns_the_analysis_after_an_outage(): void
    {
        $this->fakeGemini(500);
        $user = $this->applicant();
        $this->upload($user, 'cni');
        $this->upload($user, 'selfie');
        $this->assertSame('en_attente', $user->fresh()->kyc_status);

        $this->fakeGemini();

        $this->actingAs($user)
            ->postJson('/api/v1/kyc/verify-ai')
            ->assertOk()
            ->assertJsonPath('data.auto_approved', true)
            ->assertJsonPath('data.kyc_status', 'actif');
    }
}
