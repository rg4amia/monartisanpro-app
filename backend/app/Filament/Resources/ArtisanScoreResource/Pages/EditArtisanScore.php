<?php

namespace App\Filament\Resources\ArtisanScoreResource\Pages;

use App\Filament\Resources\ArtisanScoreResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArtisanScore extends EditRecord
{
    protected static string $resource = ArtisanScoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
