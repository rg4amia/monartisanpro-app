<?php

namespace App\Filament\Widgets;

use App\Models\ArtisanScore;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BadgeStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $total = ArtisanScore::count();
        $gold = ArtisanScore::where('badge_level', 'gold')->count();
        $silver = ArtisanScore::where('badge_level', 'silver')->count();
        $bronze = ArtisanScore::where('badge_level', 'bronze')->count();
        $noBadge = ArtisanScore::where('badge_level', 'none')->count();

        $averageScore = ArtisanScore::avg('total_score') ?? 0;

        return [
            Stat::make('Score Moyen', number_format($averageScore, 1) . '/100')
                ->description('Tous les artisans')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),

            Stat::make('Badge Or 🥇', $gold)
                ->description('Score ≥ 80, ≥ 20 projets')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('warning')
                ->extraAttributes(['class' => 'text-yellow-600']),

            Stat::make('Badge Argent 🥈', $silver)
                ->description('Score ≥ 65, ≥ 10 projets')
                ->descriptionIcon('heroicon-m-star')
                ->color('primary'),

            Stat::make('Badge Bronze 🥉', $bronze)
                ->description('Score ≥ 50, ≥ 5 projets')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('info'),

            Stat::make('Sans Badge', $noBadge)
                ->description('En cours de qualification')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('danger'),
        ];
    }
}
