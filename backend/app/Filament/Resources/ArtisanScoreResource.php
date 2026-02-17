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
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_score')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reliability_score')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('integrity_score')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quality_score')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('responsiveness_score')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('professionalism_score')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('projects_completed')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('average_rating')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_reviews')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('badge_level'),
                Tables\Columns\TextColumn::make('last_calculated_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
