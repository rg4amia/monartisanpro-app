<?php

namespace App\Filament\Resources\ArtisanScoreResource\Pages;

use App\Filament\Resources\ArtisanScoreResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArtisanScores extends ListRecords
{
    protected static string $resource = ArtisanScoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
