<?php

namespace App\Services;

use App\Models\KycDocument;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class KycService
{
    /**
     * Types de pièce d'identité acceptés pour l'auto-approbation.
     */
    private const ACCEPTED_DOCUMENT_TYPES = ['cni', 'passeport', 'attestation'];

    public function __construct(
        private GeminiService $geminiService,
        private NotificationService $notificationService,
    ) {}

    /**
     * Upload un document KYC, lance l'analyse IA (OCR de la pièce ou comparaison
     * faciale) puis tente l'auto-approbation du dossier.
     */
    public function uploadDocument(User $user, string $type, UploadedFile $file): KycDocument
    {
        // Supprime l'ancien document du même type s'il existe
        $existing = KycDocument::where('user_id', $user->id)
            ->where('type', $type)
            ->first();

        if ($existing) {
            $this->deleteStoredFile($existing);
            $existing->delete();
        }

        // Disque privé : une CNI ou un selfie ne doit pas être servi par une
        // URL publique permanente. La consultation passe par une URL signée à
        // durée limitée (cf. KycDocument::fileUrl et la route kyc.document.file).
        $path = $file->store('kyc', 'local');

        $doc = KycDocument::create([
            'user_id' => $user->id,
            'type' => $type,
            'file_url' => $path,
            'statut' => 'en_attente',
        ]);

        [$cniDoc, $selfieDoc] = $this->currentDocuments($user);

        if ($type === 'cni' && $cniDoc) {
            $this->analyzeCni($user, $cniDoc);
        }

        // Toute nouvelle pièce (CNI comme selfie) impose une nouvelle comparaison
        // faciale : une concordance établie avec une CNI remplacée depuis ne vaut
        // rien pour la nouvelle.
        if ($cniDoc && $selfieDoc) {
            $this->analyzeFace($user, $cniDoc, $selfieDoc);
        }

        $this->attemptAutoApproval($user);

        $user->refresh();

        // Notification d'attente si le compte n'est pas immédiatement validé par l'IA
        if ($user->kyc_status !== 'actif') {
            $hasPendingNotif = Notification::where('user_id', $user->id)
                ->where('type', 'kyc')
                ->where('title', 'Compte en attente de validation')
                ->exists();

            if (! $hasPendingNotif) {
                $this->notificationService->send(
                    $user,
                    'kyc',
                    'Compte en attente de validation',
                    'Votre compte est en attente de validation KYC. Nos équipes étudient actuellement vos pièces justificatives.'
                );
            }
        }

        return $doc->fresh();
    }

    /**
     * Contrôles d'auto-approbation du dossier. Renvoie la liste des motifs qui
     * s'y opposent : une liste vide signifie que le dossier peut être validé.
     *
     * @return array<int, string>
     */
    public function autoApprovalBlockers(User $user): array
    {
        [$cniDoc, $selfieDoc] = $this->currentDocuments($user);

        $blockers = [];

        if (! config('prosartisan.kyc.auto_approval_enabled', true)) {
            $blockers[] = 'auto_approbation_desactivee';
        }

        // L'IA ne fait qu'activer un dossier neuf : jamais un compte rejeté par
        // un administrateur, suspendu ou anonymisé, ni un rôle soumis à revue humaine.
        if ($user->kyc_status !== 'en_attente') {
            $blockers[] = 'statut_kyc_non_eligible';
        }
        if (! $user->isAccountActive() || $user->anonymized_at !== null) {
            $blockers[] = 'compte_inactif';
        }
        if (! in_array($user->role, (array) config('prosartisan.kyc.auto_approval_roles', []), true)) {
            $blockers[] = 'role_soumis_a_revue_humaine';
        }

        if (! $cniDoc || ! $selfieDoc) {
            $blockers[] = 'pieces_incompletes';

            return $blockers;
        }

        if ($cniDoc->statut !== 'en_attente' || $selfieDoc->statut !== 'en_attente') {
            $blockers[] = 'pieces_deja_examinees';
        }

        $ocr = $cniDoc->ocr_data ?? [];
        if (($ocr['analysis_available'] ?? false) !== true) {
            $blockers[] = 'analyse_piece_indisponible';
        } else {
            if (! in_array($ocr['document_type'] ?? null, self::ACCEPTED_DOCUMENT_TYPES, true)) {
                $blockers[] = 'type_de_piece_non_reconnu';
            }
            if (($ocr['is_legible'] ?? false) !== true) {
                $blockers[] = 'piece_illisible';
            }
            if (($ocr['has_photo'] ?? false) !== true) {
                $blockers[] = 'photo_absente';
            }
            if (($ocr['is_tampered'] ?? true) !== false) {
                $blockers[] = 'suspicion_de_falsification';
            }
            if (($ocr['is_expired'] ?? true) !== false) {
                $blockers[] = 'piece_expiree';
            }
            if ((int) ($cniDoc->ai_confidence_score ?? 0) < (int) config('prosartisan.kyc.ocr_min_quality', 70)) {
                $blockers[] = 'qualite_piece_insuffisante';
            }
            if ($cniDoc->ocr_document_number === null) {
                $blockers[] = 'numero_de_piece_illisible';
            } elseif ($this->documentNumberUsedElsewhere($user, $cniDoc->ocr_document_number)) {
                $blockers[] = 'piece_deja_utilisee_par_un_autre_compte';
            }
        }

        $face = $selfieDoc->ai_analysis ?? [];
        if (($face['analysis_available'] ?? false) !== true) {
            $blockers[] = 'analyse_biometrique_indisponible';
        } else {
            if ((int) ($face['cni_document_id'] ?? 0) !== $cniDoc->id) {
                $blockers[] = 'comparaison_faciale_obsolete';
            }
            if ($selfieDoc->face_matched !== true) {
                $blockers[] = 'visages_non_concordants';
            }
            if (($face['liveness_detected'] ?? false) !== true) {
                $blockers[] = 'defaut_de_vivacite';
            }
            if ((int) ($selfieDoc->ai_confidence_score ?? 0) < (int) config('prosartisan.kyc.auto_approval_threshold', 85)) {
                $blockers[] = 'score_biometrique_insuffisant';
            }
        }

        return $blockers;
    }

    /**
     * Tente l'auto-approbation instantanée du dossier KYC par l'IA.
     */
    public function attemptAutoApproval(User $user): bool
    {
        if ($this->autoApprovalBlockers($user) !== []) {
            return false;
        }

        [$cniDoc, $selfieDoc] = $this->currentDocuments($user);
        $now = now();

        DB::transaction(function () use ($user, $cniDoc, $selfieDoc, $now): void {
            foreach ([$cniDoc, $selfieDoc] as $doc) {
                $doc->update([
                    'statut' => 'approuve',
                    'auto_verified' => true,
                    'reviewed_by' => null,
                    'reviewed_at' => $now,
                ]);
            }

            $user->update(['kyc_status' => 'actif']);
        });

        $this->notificationService->send(
            $user,
            'kyc',
            'Compte validé',
            'Votre identité a été vérifiée automatiquement. Vous pouvez désormais effectuer vos transactions en toute sécurité.',
            ['auto_verified' => true, 'score' => $selfieDoc->ai_confidence_score]
        );

        Log::info('KYC auto-approuvé par l\'IA', [
            'user_id' => $user->id,
            'ocr_score' => $cniDoc->ai_confidence_score,
            'biometry_score' => $selfieDoc->ai_confidence_score,
        ]);

        return true;
    }

    /**
     * Relance l'analyse IA complète (OCR + biométrie) d'un dossier encore en attente.
     *
     * @return array{success: bool, auto_approved: bool, cni_score: ?int, biometry_score: ?int, blockers: array<int, string>, message: string}
     */
    public function processAiVerification(User $user): array
    {
        [$cniDoc, $selfieDoc] = $this->currentDocuments($user);

        $failure = fn (string $message) => [
            'success' => false,
            'auto_approved' => false,
            'cni_score' => null,
            'biometry_score' => null,
            'blockers' => [],
            'message' => $message,
        ];

        if ($user->kyc_status !== 'en_attente') {
            return $failure('Votre dossier KYC n\'est plus en attente de vérification.');
        }

        if (! $cniDoc || ! $selfieDoc) {
            return $failure('La pièce d\'identité et le selfie sont tous deux requis pour la vérification.');
        }

        if ($this->getDocumentBytes($cniDoc) === null || $this->getDocumentBytes($selfieDoc) === null) {
            return $failure('Vos pièces sont illisibles : merci de les téléverser à nouveau.');
        }

        $this->analyzeCni($user, $cniDoc);
        $this->analyzeFace($user, $cniDoc, $selfieDoc);

        $autoApproved = $this->attemptAutoApproval($user);
        $cniDoc->refresh();
        $selfieDoc->refresh();

        return [
            'success' => true,
            'auto_approved' => $autoApproved,
            'cni_score' => $cniDoc->ai_confidence_score,
            'biometry_score' => $selfieDoc->ai_confidence_score,
            'blockers' => $autoApproved ? [] : $this->autoApprovalBlockers($user->fresh()),
            'message' => $autoApproved
                ? 'Votre identité a été vérifiée : votre compte est actif.'
                : 'Dossier analysé. Il sera examiné par notre équipe dans les meilleurs délais.',
        ];
    }

    /**
     * OCR et contrôle d'intégrité de la pièce d'identité.
     */
    private function analyzeCni(User $user, KycDocument $cniDoc): void
    {
        $bytes = $this->getDocumentBytes($cniDoc) ?? '';
        $ocr = $this->geminiService->extractCniData($bytes, $this->getDocumentMime($cniDoc), $user->id);

        $cniDoc->update([
            'ocr_data' => $ocr,
            'ocr_document_number' => $ocr['document_number'] ?? null,
            'ai_confidence_score' => $ocr['quality_score'] ?? null,
            'ai_analysis' => $ocr,
        ]);
    }

    /**
     * Comparaison faciale pièce ↔ selfie. L'identifiant de la pièce comparée est
     * conservé : l'auto-approbation exige qu'il désigne la pièce courante.
     */
    private function analyzeFace(User $user, KycDocument $cniDoc, KycDocument $selfieDoc): void
    {
        $match = $this->geminiService->verifyFaceAndLiveness(
            $this->getDocumentBytes($cniDoc) ?? '',
            $this->getDocumentMime($cniDoc),
            $this->getDocumentBytes($selfieDoc) ?? '',
            $this->getDocumentMime($selfieDoc),
            $user->id
        );

        $selfieDoc->update([
            'ai_confidence_score' => $match['overall_confidence_score'] ?? null,
            'face_matched' => ($match['face_matched'] ?? false) === true,
            'ai_analysis' => array_merge($match, ['cni_document_id' => $cniDoc->id]),
        ]);
    }

    /**
     * Une même pièce d'identité ne peut valider qu'un seul compte.
     */
    private function documentNumberUsedElsewhere(User $user, string $documentNumber): bool
    {
        return KycDocument::where('type', 'cni')
            ->where('ocr_document_number', $documentNumber)
            ->where('user_id', '!=', $user->id)
            ->exists();
    }

    /**
     * @return array{0: ?KycDocument, 1: ?KycDocument} Pièce d'identité et selfie courants.
     */
    private function currentDocuments(User $user): array
    {
        $documents = KycDocument::where('user_id', $user->id)
            ->whereIn('type', ['cni', 'selfie'])
            ->orderByDesc('id')
            ->get();

        return [
            $documents->firstWhere('type', 'cni'),
            $documents->firstWhere('type', 'selfie'),
        ];
    }

    /**
     * Lit les octets bruts d'un document stocké, sur le disque où il se trouve.
     */
    private function getDocumentBytes(KycDocument $document): ?string
    {
        $raw = $document->storagePath();
        if ($raw === null) {
            return null;
        }

        $disk = $document->isLegacy() ? 'public' : 'local';
        $path = $document->isLegacy() ? str_replace('/storage/', '', $raw) : $raw;

        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $bytes = Storage::disk($disk)->get($path);

        return $bytes === null || $bytes === '' ? null : $bytes;
    }

    /**
     * Type MIME d'un document stocké, déduit de son extension.
     */
    private function getDocumentMime(KycDocument $document): string
    {
        $extension = strtolower(pathinfo((string) $document->storagePath(), PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    /**
     * Supprime le fichier physique d'un document, sur le disque où il se trouve
     * réellement : les pièces d'avant la migration vivent encore sur le disque
     * public, les nouvelles sur le disque privé.
     */
    private function deleteStoredFile(KycDocument $document): void
    {
        $raw = $document->storagePath();

        if ($raw === null) {
            return;
        }

        if ($document->isLegacy()) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $raw));

            return;
        }

        Storage::disk('local')->delete($raw);
    }

    /**
     * Retourne le statut KYC de l'utilisateur avec les résultats de l'analyse IA.
     */
    public function getStatus(User $user): array
    {
        [$cni, $selfie] = $this->currentDocuments($user);

        return [
            'kyc_status' => $user->kyc_status,
            'is_auto_verified' => (bool) ($cni?->auto_verified && $selfie?->auto_verified),
            'documents' => [
                'cni' => $cni ? [
                    'statut' => $cni->statut,
                    'file_url' => $cni->file_url,
                    'auto_verified' => (bool) $cni->auto_verified,
                    'ai_confidence_score' => $cni->ai_confidence_score,
                    'ocr_data' => (object) ($cni->ocr_data ?? []),
                ] : null,
                'selfie' => $selfie ? [
                    'statut' => $selfie->statut,
                    'file_url' => $selfie->file_url,
                    'auto_verified' => (bool) $selfie->auto_verified,
                    'ai_confidence_score' => $selfie->ai_confidence_score,
                    'face_matched' => (bool) $selfie->face_matched,
                ] : null,
            ],
            'can_transact' => $user->kyc_status === 'actif',
        ];
    }

    /**
     * Vérifie si l'utilisateur a les 2 documents approuvés.
     */
    public function isKycComplete(User $user): bool
    {
        return KycDocument::where('user_id', $user->id)
            ->where('statut', 'approuve')
            ->whereIn('type', ['cni', 'selfie'])
            ->count() === 2;
    }
}
