<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class KycDocument extends Model
{
    /**
     * Durée de validité d'un lien de consultation d'une pièce KYC.
     */
    private const VIEW_URL_TTL_MINUTES = 15;

    protected $fillable = [
        'user_id', 'type', 'file_url', 'statut',
        'reviewed_by', 'rejection_reason', 'reviewed_at',
        'ocr_data', 'ocr_document_number', 'ai_confidence_score', 'ai_analysis',
        'auto_verified', 'face_matched',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'ocr_data' => 'array',
            'ai_analysis' => 'array',
            'ai_confidence_score' => 'integer',
            'auto_verified' => 'boolean',
            'face_matched' => 'boolean',
        ];
    }

    /**
     * Les pièces KYC (CNI, selfie) sont désormais stockées sur un disque privé
     * et la colonne ne contient plus qu'un chemin interne. On expose à sa place
     * une URL signée à durée limitée : une pièce d'identité ne doit pas rester
     * consultable indéfiniment par quiconque a vu passer son lien une fois.
     *
     * Les enregistrements historiques, écrits sur le disque public, portent
     * déjà une URL absolue : on les renvoie tels quels pour ne pas casser
     * l'affichage des dossiers existants.
     */
    protected function fileUrl(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value === null || $value === '') {
                    return null;
                }

                if ($this->isLegacyPublicUrl($value)) {
                    return $value;
                }

                return URL::temporarySignedRoute(
                    'kyc.document.file',
                    now()->addMinutes(self::VIEW_URL_TTL_MINUTES),
                    ['document' => $this->getKey()],
                );
            },
        );
    }

    /**
     * Chemin brut tel qu'il est stocké en base (sans transformation).
     */
    public function storagePath(): ?string
    {
        $raw = $this->getRawOriginal('file_url');

        return $raw === '' ? null : $raw;
    }

    /**
     * Un enregistrement d'avant la migration vers le disque privé.
     */
    public function isLegacy(): bool
    {
        $raw = $this->storagePath();

        return $raw !== null && $this->isLegacyPublicUrl($raw);
    }

    private function isLegacyPublicUrl(string $value): bool
    {
        return str_starts_with($value, 'http://')
            || str_starts_with($value, 'https://')
            || str_starts_with($value, '/storage/');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
