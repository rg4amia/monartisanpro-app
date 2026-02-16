<?php

namespace App\Filament\Resources\KycDocumentResource\Pages;

use App\Filament\Resources\KycDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKycDocuments extends ListRecords
{
    protected static string $resource = KycDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
