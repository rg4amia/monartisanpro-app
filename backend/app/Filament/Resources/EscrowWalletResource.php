<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EscrowWalletResource\Pages;
use App\Filament\Resources\EscrowWalletResource\RelationManagers;
use App\Models\EscrowWallet;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EscrowWalletResource extends Resource
{
    protected static ?string $model = EscrowWallet::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Portefeuilles Séquestre';

    protected static ?string $navigationGroup = 'Finance & Paiements';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'portefeuille séquestre';

    protected static ?string $pluralModelLabel = 'portefeuilles séquestre';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Projet')
                    ->schema([
                        Forms\Components\Select::make('project_id')
                            ->label('Projet')
                            ->relationship('project', 'title')
                            ->searchable()
                            ->required()
                            ->disabled(fn ($context) => $context === 'edit'),
                    ]),

                Forms\Components\Section::make('Portefeuilles')
                    ->schema([
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Montant total (XOF)')
                            ->required()
                            ->numeric()
                            ->prefix('XOF')
                            ->disabled(),
                        Forms\Components\TextInput::make('material_wallet')
                            ->label('Portefeuille matériel (XOF)')
                            ->required()
                            ->numeric()
                            ->prefix('XOF')
                            ->disabled(),
                        Forms\Components\TextInput::make('labor_wallet')
                            ->label('Portefeuille main-d\'œuvre (XOF)')
                            ->required()
                            ->numeric()
                            ->prefix('XOF')
                            ->disabled(),
                    ])->columns(3),

                Forms\Components\Section::make('Dépenses')
                    ->schema([
                        Forms\Components\TextInput::make('material_spent')
                            ->label('Matériel dépensé (XOF)')
                            ->required()
                            ->numeric()
                            ->prefix('XOF')
                            ->disabled()
                            ->default(0.00),
                        Forms\Components\TextInput::make('labor_released')
                            ->label('Main-d\'œuvre libérée (XOF)')
                            ->required()
                            ->numeric()
                            ->prefix('XOF')
                            ->disabled()
                            ->default(0.00),
                    ])->columns(2),

                Forms\Components\Section::make('Soldes calculés')
                    ->schema([
                        Forms\Components\Placeholder::make('material_balance')
                            ->label('Solde matériel')
                            ->content(fn (?EscrowWallet $record): string =>
                                $record ? number_format($record->material_wallet - $record->material_spent, 0, ',', ' ') . ' XOF' : '-'
                            ),
                        Forms\Components\Placeholder::make('labor_balance')
                            ->label('Solde main-d\'œuvre')
                            ->content(fn (?EscrowWallet $record): string =>
                                $record ? number_format($record->labor_wallet - $record->labor_released, 0, ',', ' ') . ' XOF' : '-'
                            ),
                    ])->columns(2),

                Forms\Components\Section::make('Statut')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Statut')
                            ->required()
                            ->options([
                                'active' => 'Actif',
                                'completed' => 'Complété',
                                'refunded' => 'Remboursé',
                            ])
                            ->default('active'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('project.title')
                    ->label('Projet')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('project.client.name')
                    ->label('Client')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('project.artisan.name')
                    ->label('Artisan')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('XOF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('material_balance')
                    ->label('Solde matériel')
                    ->getStateUsing(fn (EscrowWallet $record): float =>
                        $record->material_wallet - $record->material_spent
                    )
                    ->money('XOF')
                    ->color(fn (float $state): string => match (true) {
                        $state <= 0 => 'danger',
                        $state < 50000 => 'warning',
                        default => 'success',
                    })
                    ->sortable(false),
                Tables\Columns\TextColumn::make('labor_balance')
                    ->label('Solde main-d\'œuvre')
                    ->getStateUsing(fn (EscrowWallet $record): float =>
                        $record->labor_wallet - $record->labor_released
                    )
                    ->money('XOF')
                    ->color(fn (float $state): string => match (true) {
                        $state <= 0 => 'danger',
                        $state < 50000 => 'warning',
                        default => 'success',
                    })
                    ->sortable(false),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Statut')
                    ->colors([
                        'success' => 'active',
                        'info' => 'completed',
                        'warning' => 'refunded',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Actif',
                        'completed' => 'Complété',
                        'refunded' => 'Remboursé',
                        default => $state,
                    }),
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
                        'completed' => 'Complété',
                        'refunded' => 'Remboursé',
                    ]),
                Tables\Filters\SelectFilter::make('project.status')
                    ->label('Statut du projet')
                    ->relationship('project', 'status')
                    ->options([
                        'in_progress' => 'En cours',
                        'completed' => 'Terminé',
                        'cancelled' => 'Annulé',
                        'disputed' => 'En litige',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('manual_labor_release')
                    ->label('Libérer main-d\'œuvre')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('warning')
                    ->visible(fn (EscrowWallet $record): bool =>
                        $record->status === 'active' &&
                        ($record->labor_wallet - $record->labor_released) > 0
                    )
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Montant à libérer (XOF)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(fn (EscrowWallet $record): float =>
                                $record->labor_wallet - $record->labor_released
                            )
                            ->prefix('XOF'),
                        Forms\Components\Textarea::make('reason')
                            ->label('Raison')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (EscrowWallet $record, array $data): void {
                        // Update labor released
                        $record->update([
                            'labor_released' => $record->labor_released + $data['amount'],
                        ]);

                        // Create transaction record
                        Transaction::create([
                            'project_id' => $record->project_id,
                            'user_id' => $record->project->artisan_id,
                            'transaction_ref' => 'MANUAL-' . now()->format('YmdHis'),
                            'type' => 'labor_release',
                            'amount' => $data['amount'],
                            'payment_method' => 'manual',
                            'status' => 'completed',
                            'metadata' => [
                                'reason' => $data['reason'],
                                'released_by' => auth()->id(),
                                'released_at' => now()->toDateTimeString(),
                            ],
                        ]);
                    }),
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
            'index' => Pages\ListEscrowWallets::route('/'),
            'create' => Pages\CreateEscrowWallet::route('/create'),
            'edit' => Pages\EditEscrowWallet::route('/{record}/edit'),
        ];
    }
}
