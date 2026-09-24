<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Address extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'recipient_name',
        'recipient_phone',
        'address_line',
        'city',
        'region',
        'country',
        'is_default',
    ];

    protected $hidden = ['position'];

    protected $appends = ['coordinates'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getCoordinatesAttribute(): ?array
    {
        return $this->getPositionCoords();
    }

    public function setPosition(float $lat, float $lng): void
    {
        if (config('database.default') === 'sqlite') {
            $this->forceFill(['position' => "$lat,$lng"])->save();

            return;
        }

        DB::statement(
            'UPDATE addresses SET position = POINT(?, ?) WHERE id = ?',
            [$lng, $lat, $this->id]
        );
    }

    public function getPositionCoords(): ?array
    {
        if (config('database.default') === 'sqlite') {
            if (! is_string($this->position) || ! str_contains($this->position, ',')) {
                return null;
            }

            [$lat, $lng] = array_map('trim', explode(',', $this->position, 2));

            return [
                'lat' => (float) $lat,
                'lng' => (float) $lng,
            ];
        }

        $row = DB::selectOne(
            'SELECT ST_X(position) as lng, ST_Y(position) as lat FROM addresses WHERE id = ?',
            [$this->id]
        );

        return $row && $row->lat !== null ? ['lat' => (float) $row->lat, 'lng' => (float) $row->lng] : null;
    }
}
