<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\URL;

class RecruitmentApplication extends Model
{
    /** Durée de validité de l'URL signée d'écoute de la note vocale. */
    private const VOICE_NOTE_URL_TTL_MINUTES = 15;

    protected $fillable = [
        'offer_id', 'artisan_id', 'matching_score', 'status', 'applied_at',
        'voice_note_path', 'voice_note_duration', 'voice_transcription', 'voice_status',
    ];

    /** Le chemin interne du fichier n'est jamais exposé : seule l'URL signée l'est. */
    protected $hidden = ['voice_note_path'];

    protected $appends = ['voice_note_url'];

    protected function casts(): array
    {
        return [
            'matching_score' => 'decimal:2',
            'applied_at' => 'datetime',
            'voice_note_duration' => 'integer',
        ];
    }

    /**
     * URL signée (15 min) d'écoute de la note vocale, uniquement une fois la
     * transcription validée sans coordonnées : une note non vérifiée n'est
     * jamais servie, elle pourrait contourner le masquage du numéro.
     */
    protected function voiceNoteUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->voice_status === 'approved' && $this->voice_note_path
            ? URL::temporarySignedRoute(
                'recruitment.voice-note.file',
                now()->addMinutes(self::VOICE_NOTE_URL_TTL_MINUTES),
                ['application' => $this->getKey()],
            )
            : null);
    }

    /** Transcription exposée uniquement après validation (même règle que l'audio). */
    protected function voiceTranscription(): Attribute
    {
        return Attribute::get(fn (?string $value): ?string => $this->voice_status === 'approved' ? $value : null);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(RecruitmentOffer::class, 'offer_id');
    }

    public function artisan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }

    public function engagement(): HasOne
    {
        return $this->hasOne(RecruitmentEngagement::class, 'application_id');
    }
}
