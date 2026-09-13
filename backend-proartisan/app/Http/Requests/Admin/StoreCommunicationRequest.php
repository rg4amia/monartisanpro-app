<?php

namespace App\Http\Requests\Admin;

use App\Models\Communication;
use App\Services\UploadLimitService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunicationRequest extends FormRequest
{
    /** Formats vocaux acceptés — ceux que lisent nativement Android et iOS. */
    public const AUDIO_MIMES = 'mp3,mpga,m4a,aac,ogg,oga,wav,webm';

    /** 10 Mo : ~20 minutes de parole à 64 kbps, largement au-delà du besoin. */
    public const AUDIO_MAX_KB = 10240;

    /**
     * Plafond réellement applicable, borné par la configuration PHP.
     *
     * Sur un hébergement mutualisé, `upload_max_filesize` descend souvent
     * sous cette valeur : PHP coupe alors la requête avant Laravel et
     * l'administrateur reçoit une erreur sans rapport avec le fichier.
     */
    public static function audioMaxKilobytes(): int
    {
        return app(UploadLimitService::class)->maxKilobytes(self::AUDIO_MAX_KB);
    }

    public function authorize(): bool
    {
        return true;
    }

    /** Une modification conserve le média déjà en place si aucun n'est renvoyé. */
    private function isUpdate(): bool
    {
        return $this->route('communication') !== null;
    }

    public function rules(): array
    {
        $isAudio = $this->input('type') === Communication::TYPE_AUDIO;
        $isVideo = $this->input('type') === Communication::TYPE_VIDEO;

        return [
            'type'     => ['required', 'string', Rule::in(Communication::TYPES)],
            'titre'    => ['required', 'string', 'max:255'],
            'contenu'  => ['required', 'string'],
            'cibles'   => ['required', 'array', 'min:1'],
            'cibles.*' => ['required', 'string', Rule::in(['client', 'artisan', 'fournisseur', 'livreur'])],

            // Contenu vocal : fichier téléversé, obligatoire à la création.
            'media_file' => [
                $isAudio && ! $this->isUpdate() ? 'required' : 'nullable',
                'file',
                'mimes:'.self::AUDIO_MIMES,
                'max:'.self::audioMaxKilobytes(),
            ],

            // Contenu vidéo : lien vers la plateforme d'hébergement.
            // `https` est exigé, pas seulement recommandé : le manifeste Android
            // de production interdit le trafic en clair, donc une URL `http://`
            // ne se chargerait tout simplement pas sur l'application publiée.
            'media_external_url' => [
                $isVideo && ! $this->isUpdate() ? 'required' : 'nullable',
                'url',
                'starts_with:https://',
                'max:2048',
            ],

            'media_duration' => ['nullable', 'integer', 'min:1', 'max:86400'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'    => 'Le type de communication est obligatoire.',
            'type.in'          => 'Le type doit être : annonce, le_saviez_vous, audio ou video.',
            'titre.required'   => 'Le titre est obligatoire.',
            'titre.max'        => 'Le titre ne peut pas dépasser 255 caractères.',
            'contenu.required' => 'Le contenu est obligatoire — il sert de résumé écrit à ceux qui ne liront pas le média.',
            'cibles.required'  => 'Vous devez sélectionner au moins un espace cible.',
            'cibles.min'       => 'Vous devez sélectionner au moins un espace cible.',
            'cibles.*.in'      => 'Chaque cible doit être : client, artisan, fournisseur ou livreur.',

            'media_file.required' => 'Un fichier audio est obligatoire pour une publication vocale.',
            'media_file.mimes'    => 'Format audio non pris en charge. Formats acceptés : MP3, M4A, AAC, OGG, WAV.',
            'media_file.max'      => 'Le fichier audio ne peut pas dépasser '
                .app(UploadLimitService::class)->humanLimit(self::AUDIO_MAX_KB).'.',

            'media_external_url.required'    => 'Un lien vers la vidéo est obligatoire pour une publication vidéo.',
            'media_external_url.url'         => 'Le lien de la vidéo n\'est pas une URL valide.',
            'media_external_url.starts_with' => 'Le lien doit être en HTTPS : l\'application refuse le trafic non chiffré et la vidéo ne se chargerait pas.',
        ];
    }
}
