<?php

namespace App\Filament\Resources\EscrowWalletResource\Pages;

use App\Filament\Resources\EscrowWalletResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEscrowWallets extends ListRecords
{
    protected static string $resource = EscrowWalletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
