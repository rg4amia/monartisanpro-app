<?php

namespace App\Filament\Widgets;

use App\Models\KycDocument;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KycStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $pendingCount = KycDocument::where('verification_status', 'pending')->count();
        $approvedCount = KycDocument::where('verification_status', 'approved')->count();
        $rejectedCount = KycDocument::where('verification_status', 'rejected')->count();

        return [
            Stat::make('KYC en attente', $pendingCount)
                ->description('Documents en attente de vérification')
                ->color('warning')
                ->icon('heroicon-o-clock'),
            Stat::make('KYC approuvés', $approvedCount)
                ->description('Documents approuvés')
                ->color('success')
                ->icon('heroicon-o-check-circle'),
            Stat::make('KYC rejetés', $rejectedCount)
                ->description('Documents rejetés')
                ->color('danger')
                ->icon('heroicon-o-x-circle'),
        ];
    }
}
