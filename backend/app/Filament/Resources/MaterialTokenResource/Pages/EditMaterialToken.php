<?php

namespace App\Filament\Resources\MaterialTokenResource\Pages;

use App\Filament\Resources\MaterialTokenResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterialToken extends EditRecord
{
    protected static string $resource = MaterialTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
