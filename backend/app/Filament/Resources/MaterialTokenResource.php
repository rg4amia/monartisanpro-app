<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialTokenResource\Pages;
use App\Filament\Resources\MaterialTokenResource\RelationManagers;
use App\Models\MaterialToken;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MaterialTokenResource extends Resource
{
    protected static ?string $model = MaterialToken::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Jetons Matériel';

    protected static ?string $modelLabel = 'jeton';

    protected static ?string $pluralModelLabel = 'jetons';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informations du jeton')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(20)
                            ->disabled()
                            ->default(fn () => 'PA-' . strtoupper(substr(md5(uniqid()), 0, 6))),
                        Forms\Components\Select::make('project_id')
                            ->label('Projet')
                            ->relationship('project', 'title')
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('vendor_id')
                            ->label('Fournisseur')
                            ->relationship('vendor', 'name')
                            ->searchable(),
                    ])->columns(3),

                Forms\Components\Section::make('Valeurs')
                    ->schema([
                        Forms\Components\TextInput::make('total_value')
                            ->label('Valeur totale (XOF)')
                            ->required()
                            ->numeric()
                            ->prefix('XOF'),
                        Forms\Components\TextInput::make('remaining_value')
                            ->label('Valeur restante (XOF)')
                            ->required()
                            ->numeric()
                            ->prefix('XOF'),
                    ])->columns(2),

                Forms\Components\Section::make('Statut et expiration')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Statut')
                            ->options([
                                'active' => 'Actif',
                                'partially_used' => 'Partiellement utilisé',
                                'fully_used' => 'Entièrement utilisé',
                                'expired' => 'Expiré',
                            ])
                            ->required(),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expire le')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->weight('medium')
                    ->copyable(),
                Tables\Columns\TextColumn::make('project.title')
                    ->label('Projet')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('vendor.name')
                    ->label('Fournisseur')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Non utilisé'),
                Tables\Columns\TextColumn::make('total_value')
                    ->label('Valeur totale')
                    ->money('XOF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('remaining_value')
                    ->label('Valeur restante')
                    ->money('XOF')
                    ->sortable()
                    ->color(fn (MaterialToken $record): string => $record->remaining_value == 0 ? 'danger' : 'success'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Statut')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'partially_used',
                        'danger' => 'fully_used',
                        'danger' => 'expired',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Actif',
                        'partially_used' => 'Partiellement utilisé',
                        'fully_used' => 'Entièrement utilisé',
                        'expired' => 'Expiré',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expire le')
                    ->date('d/m/Y H:i')
                    ->sortable()
                    ->color(function (MaterialToken $record): string {
                        if ($record->expires_at->isPast()) {
                            return 'danger';
                        }
                        if ($record->expires_at->diffInHours() < 24) {
                            return 'warning';
                        }
                        return 'success';
                    }),
                Tables\Columns\IconColumn::make('fraud_alert')
                    ->label('⚠️ GPS')
                    ->boolean()
                    ->getStateUsing(function (MaterialToken $record): bool {
                        // Check if any redemption has distance > 100m
                        return $record->redemptions()
                            ->where('distance_meters', '>', 100)
                            ->exists();
                    })
                    ->color(fn (bool $state): string => $state ? 'danger' : 'success')
                    ->tooltip(fn (bool $state): string => $state ? 'Violation GPS détectée (>100m)' : 'GPS OK'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'active' => 'Actif',
                        'partially_used' => 'Partiellement utilisé',
                        'fully_used' => 'Entièrement utilisé',
                        'expired' => 'Expiré',
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('vendor_id')
                    ->label('Fournisseur')
                    ->relationship('vendor', 'name')
                    ->searchable(),
                Tables\Filters\Filter::make('expires_at')
                    ->form([
                        Forms\Components\DatePicker::make('expires_from')
                            ->label('Expire à partir du'),
                        Forms\Components\DatePicker::make('expires_until')
                            ->label('Expire jusqu\'au'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['expires_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('expires_at', '>=', $date),
                            )
                            ->when(
                                $data['expires_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('expires_at', '<=', $date),
                            );
                    }),
                Tables\Filters\Filter::make('gps_violations')
                    ->label('Violations GPS')
                    ->query(fn (Builder $query): Builder => $query->whereHas('redemptions', function ($q) {
                        $q->where('distance_meters', '>', 100);
                    })),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('view_qr')
                    ->label('Voir QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->color('primary')
                    ->modalContent(fn (MaterialToken $record) => view('filament.modals.qr-code', ['qrPath' => $record->qr_code_path]))
                    ->modalWidth('md'),
                Tables\Actions\Action::make('expire')
                    ->label('Expirer')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (MaterialToken $record): bool => $record->status !== 'expired')
                    ->requiresConfirmation()
                    ->action(function (MaterialToken $record): void {
                        $record->update(['status' => 'expired']);
                    }),
                Tables\Actions\Action::make('manual_redemption')
                    ->label('Utilisation manuelle')
                    ->icon('heroicon-o-check-circle')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Montant (XOF)')
                            ->numeric()
                            ->required()
                            ->prefix('XOF'),
                        Forms\Components\Textarea::make('reason')
                            ->label('Raison (admin override)')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (MaterialToken $record, array $data): void {
                        // Create manual redemption with admin override
                        $record->redemptions()->create([
                            'vendor_id' => auth()->id(),
                            'amount' => $data['amount'],
                            'validation_method' => 'admin_override',
                            'redeemed_at' => now(),
                        ]);

                        $record->decrement('remaining_value', $data['amount']);
                        if ($record->remaining_value == 0) {
                            $record->update(['status' => 'fully_used']);
                        } else {
                            $record->update(['status' => 'partially_used']);
                        }
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RedemptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterialTokens::route('/'),
            'create' => Pages\CreateMaterialToken::route('/create'),
            'edit' => Pages\EditMaterialToken::route('/{record}/edit'),
        ];
    }
}
