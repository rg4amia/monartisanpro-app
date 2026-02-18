<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArtisanScoreResource\Pages;
use App\Filament\Resources\ArtisanScoreResource\RelationManagers;
use App\Models\ArtisanScore;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ArtisanScoreResource extends Resource
{
    protected static ?string $model = ArtisanScore::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Scores N\'Zassa';

    protected static ?string $navigationGroup = 'Scoring & Performance';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'score';

    protected static ?string $pluralModelLabel = 'scores';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Artisan')
                    ->schema([
                        Forms\Components\Select::make('artisan_id')
                            ->label('Artisan')
                            ->relationship('artisan', 'name')
                            ->searchable()
                            ->required()
                            ->disabled(),
                    ]),

                Forms\Components\Section::make('Score Global')
                    ->schema([
                        Forms\Components\TextInput::make('total_score')
                            ->label('Score Total (sur 100)')
                            ->numeric()
                            ->disabled()
                            ->suffix('/100')
                            ->columnSpan(1),
                        Forms\Components\Select::make('badge_level')
                            ->label('Niveau de Badge')
                            ->options([
                                'none' => 'Aucun',
                                'bronze' => 'Bronze',
                                'silver' => 'Argent',
                                'gold' => 'Or',
                            ])
                            ->disabled()
                            ->columnSpan(1),
                    ])->columns(2),

                Forms\Components\Section::make('Composantes du Score (Pondération)')
                    ->description('Fiabilité (40%), Intégrité (30%), Qualité (15%), Réactivité (10%), Professionnalisme (5%)')
                    ->schema([
                        Forms\Components\TextInput::make('reliability_score')
                            ->label('Fiabilité (40%)')
                            ->numeric()
                            ->disabled()
                            ->suffix('/40'),
                        Forms\Components\TextInput::make('integrity_score')
                            ->label('Intégrité (30%)')
                            ->numeric()
                            ->disabled()
                            ->suffix('/30'),
                        Forms\Components\TextInput::make('quality_score')
                            ->label('Qualité (15%)')
                            ->numeric()
                            ->disabled()
                            ->suffix('/15'),
                        Forms\Components\TextInput::make('responsiveness_score')
                            ->label('Réactivité (10%)')
                            ->numeric()
                            ->disabled()
                            ->suffix('/10'),
                        Forms\Components\TextInput::make('professionalism_score')
                            ->label('Professionnalisme (5%)')
                            ->numeric()
                            ->disabled()
                            ->suffix('/5'),
                    ])->columns(3),

                Forms\Components\Section::make('Statistiques')
                    ->schema([
                        Forms\Components\TextInput::make('projects_completed')
                            ->label('Projets complétés')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\TextInput::make('average_rating')
                            ->label('Note moyenne')
                            ->numeric()
                            ->disabled()
                            ->suffix('/5'),
                        Forms\Components\TextInput::make('total_reviews')
                            ->label('Nombre d\'avis')
                            ->numeric()
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('last_calculated_at')
                            ->label('Dernier calcul')
                            ->disabled(),
                    ])->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('artisan.name')
                    ->label('Artisan')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('artisan.artisanProfile.trade.name')
                    ->label('Métier')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_score')
                    ->label('Score Total')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->suffix('/100')
                    ->weight('bold')
                    ->color(fn (float $state): string => match (true) {
                        $state >= 80 => 'success',
                        $state >= 65 => 'warning',
                        $state >= 50 => 'primary',
                        default => 'danger',
                    }),
                Tables\Columns\BadgeColumn::make('badge_level')
                    ->label('Badge')
                    ->colors([
                        'warning' => 'gold',
                        'primary' => 'silver',
                        'info' => 'bronze',
                        'danger' => 'none',
                    ])
                    ->icons([
                        'heroicon-o-trophy' => 'gold',
                        'heroicon-o-star' => 'silver',
                        'heroicon-o-shield-check' => 'bronze',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'gold' => 'Or 🥇',
                        'silver' => 'Argent 🥈',
                        'bronze' => 'Bronze 🥉',
                        default => 'Aucun',
                    }),
                Tables\Columns\TextColumn::make('component_breakdown')
                    ->label('Détail des Composantes')
                    ->getStateUsing(function (ArtisanScore $record): string {
                        return sprintf(
                            'F: %.1f | I: %.1f | Q: %.1f | R: %.1f | P: %.1f',
                            $record->reliability_score,
                            $record->integrity_score,
                            $record->quality_score,
                            $record->responsiveness_score,
                            $record->professionalism_score
                        );
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('projects_completed')
                    ->label('Projets')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('average_rating')
                    ->label('Note Moyenne')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->suffix('/5')
                    ->color(fn (float $state): string => $state >= 4.0 ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('last_calculated_at')
                    ->label('Dernier Calcul')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('badge_level')
                    ->label('Niveau de Badge')
                    ->options([
                        'gold' => 'Or 🥇',
                        'silver' => 'Argent 🥈',
                        'bronze' => 'Bronze 🥉',
                        'none' => 'Aucun',
                    ])
                    ->multiple(),
                Tables\Filters\Filter::make('score_range')
                    ->form([
                        Forms\Components\TextInput::make('score_min')
                            ->label('Score minimum')
                            ->numeric()
                            ->placeholder('0'),
                        Forms\Components\TextInput::make('score_max')
                            ->label('Score maximum')
                            ->numeric()
                            ->placeholder('100'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['score_min'],
                                fn (Builder $query, $score): Builder => $query->where('total_score', '>=', $score),
                            )
                            ->when(
                                $data['score_max'],
                                fn (Builder $query, $score): Builder => $query->where('total_score', '<=', $score),
                            );
                    }),
                Tables\Filters\SelectFilter::make('trade_id')
                    ->label('Métier')
                    ->relationship('artisan.artisanProfile.trade', 'name')
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('recalculate')
                    ->label('Recalculer')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (ArtisanScore $record): void {
                        // This will trigger the ScoreController@recalculate endpoint
                        // For now, just update the last_calculated_at timestamp
                        $record->update(['last_calculated_at' => now()]);
                    })
                    ->successNotificationTitle('Score recalculé avec succès'),
                Tables\Actions\Action::make('view_calculation')
                    ->label('Détails du Calcul')
                    ->icon('heroicon-o-calculator')
                    ->color('info')
                    ->modalHeading('Détails du Calcul N\'Zassa')
                    ->modalContent(fn (ArtisanScore $record): \Illuminate\View\View => view(
                        'filament.modals.score-calculation-details',
                        ['record' => $record]
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),
                Tables\Actions\Action::make('admin_override')
                    ->label('Ajustement Admin')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Ajustement Administrateur du Score')
                    ->modalDescription('Attention : Cette action modifie manuellement le score de l\'artisan. Un historique sera créé.')
                    ->form([
                        Forms\Components\TextInput::make('new_total_score')
                            ->label('Nouveau Score Total')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('/100')
                            ->default(fn (ArtisanScore $record): float => $record->total_score),
                        Forms\Components\Textarea::make('override_reason')
                            ->label('Raison de l\'ajustement')
                            ->required()
                            ->rows(4)
                            ->placeholder('Expliquez pourquoi ce score est ajusté manuellement'),
                        Forms\Components\Toggle::make('send_notification')
                            ->label('Notifier l\'artisan')
                            ->default(true),
                    ])
                    ->action(function (ArtisanScore $record, array $data): void {
                        // Store old score for history
                        $oldScore = $record->total_score;

                        // Update the score
                        $record->update([
                            'total_score' => $data['new_total_score'],
                            'last_calculated_at' => now(),
                        ]);

                        // Recalculate badge level based on new score
                        $badgeLevel = 'none';
                        $weights = config('scoring.badge_thresholds');

                        if ($data['new_total_score'] >= $weights['gold']['score'] &&
                            $record->projects_completed >= $weights['gold']['projects']) {
                            $badgeLevel = 'gold';
                        } elseif ($data['new_total_score'] >= $weights['silver']['score'] &&
                                  $record->projects_completed >= $weights['silver']['projects']) {
                            $badgeLevel = 'silver';
                        } elseif ($data['new_total_score'] >= $weights['bronze']['score'] &&
                                  $record->projects_completed >= $weights['bronze']['projects']) {
                            $badgeLevel = 'bronze';
                        }

                        $record->update(['badge_level' => $badgeLevel]);

                        // Create history record
                        \App\Models\ScoreHistory::create([
                            'artisan_id' => $record->artisan_id,
                            'total_score' => $data['new_total_score'],
                            'reliability_score' => $record->reliability_score,
                            'integrity_score' => $record->integrity_score,
                            'quality_score' => $record->quality_score,
                            'responsiveness_score' => $record->responsiveness_score,
                            'professionalism_score' => $record->professionalism_score,
                            'event_type' => 'bonus',
                            'event_description' => 'Ajustement administrateur: ' . $data['override_reason'],
                            'score_change' => $data['new_total_score'] - $oldScore,
                            'triggered_by' => auth()->id(),
                        ]);

                        // TODO: Send notification if requested
                    })
                    ->successNotificationTitle('Score ajusté avec succès'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('total_score', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArtisanScores::route('/'),
            'create' => Pages\CreateArtisanScore::route('/create'),
            'edit' => Pages\EditArtisanScore::route('/{record}/edit'),
        ];
    }
}
