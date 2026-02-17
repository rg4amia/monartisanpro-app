<?php

namespace App\Filament\Resources\MaterialTokenResource\Pages;

use App\Filament\Resources\MaterialTokenResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterialTokens extends ListRecords
{
    protected static string $resource = MaterialTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
