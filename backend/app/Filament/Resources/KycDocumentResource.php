<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KycDocumentResource\Pages;
use App\Filament\Resources\KycDocumentResource\RelationManagers;
use App\Models\KycDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class KycDocumentResource extends Resource
{
    protected static ?string $model = KycDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Documents KYC';

    protected static ?string $navigationGroup = 'Utilisateurs & KYC';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'document KYC';

    protected static ?string $pluralModelLabel = 'documents KYC';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Utilisateur')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Utilisateur')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->required()
                            ->preload(),
                        Forms\Components\Select::make('document_type')
                            ->label('Type de document')
                            ->required()
                            ->options([
                                'cni' => 'CNI',
                                'passport' => 'Passeport',
                                'attestation' => 'Attestation',
                            ]),
                    ])->columns(2),

                Forms\Components\Section::make('Documents')
                    ->schema([
                        Forms\Components\FileUpload::make('document_path')
                            ->label('Document d\'identité')
                            ->required()
                            ->image()
                            ->directory('kyc/documents')
                            ->imagePreviewHeight('250'),
                        Forms\Components\FileUpload::make('selfie_path')
                            ->label('Photo selfie')
                            ->image()
                            ->directory('kyc/selfies')
                            ->imagePreviewHeight('250'),
                    ])->columns(2),

                Forms\Components\Section::make('Vérification')
                    ->schema([
                        Forms\Components\Select::make('verification_status')
                            ->label('Statut de vérification')
                            ->required()
                            ->default('pending')
                            ->options([
                                'pending' => 'En attente',
                                'approved' => 'Approuvé',
                                'rejected' => 'Rejeté',
                            ])
                            ->disabled(fn ($context) => $context === 'edit'),
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Raison du rejet')
                            ->rows(3)
                            ->visible(fn ($get) => $get('verification_status') === 'rejected')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('verified_by')
                            ->label('Vérifié par')
                            ->relationship('verifier', 'name')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('verified_at')
                            ->label('Vérifié le')
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                Tables\Columns\BadgeColumn::make('user.role')
                    ->label('Rôle')
                    ->colors([
                        'primary' => 'client',
                        'warning' => 'artisan',
                        'info' => 'fournisseur',
                    ])
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'client' => 'Client',
                        'artisan' => 'Artisan',
                        'fournisseur' => 'Fournisseur',
                        default => $state,
                    }),
                Tables\Columns\BadgeColumn::make('document_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'cni',
                        'info' => 'passport',
                        'warning' => 'attestation',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cni' => 'CNI',
                        'passport' => 'Passeport',
                        'attestation' => 'Attestation',
                        default => $state,
                    }),
                Tables\Columns\ImageColumn::make('document_path')
                    ->label('Document')
                    ->circular()
                    ->size(40),
                Tables\Columns\ImageColumn::make('selfie_path')
                    ->label('Selfie')
                    ->circular()
                    ->size(40),
                Tables\Columns\BadgeColumn::make('verification_status')
                    ->label('Statut')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'En attente',
                        'approved' => 'Approuvé',
                        'rejected' => 'Rejeté',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('verifier.name')
                    ->label('Vérifié par')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('verified_at')
                    ->label('Vérifié le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('verification_status')
                    ->label('Statut')
                    ->multiple()
                    ->options([
                        'pending' => 'En attente',
                        'approved' => 'Approuvé',
                        'rejected' => 'Rejeté',
                    ]),
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Type de document')
                    ->options([
                        'cni' => 'CNI',
                        'passport' => 'Passeport',
                        'attestation' => 'Attestation',
                    ]),
                Tables\Filters\SelectFilter::make('user.role')
                    ->label('Rôle utilisateur')
                    ->relationship('user', 'role')
                    ->options([
                        'client' => 'Client',
                        'artisan' => 'Artisan',
                        'fournisseur' => 'Fournisseur',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Créé du'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Créé jusqu\'au'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approuver')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (KycDocument $record): bool => $record->verification_status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Approuver le document KYC')
                    ->modalDescription('Êtes-vous sûr de vouloir approuver ce document KYC ? L\'utilisateur sera notifié.')
                    ->action(function (KycDocument $record): void {
                        $record->update([
                            'verification_status' => 'approved',
                            'verified_by' => auth()->id(),
                            'verified_at' => now(),
                        ]);

                        // Update user KYC status
                        $record->user->update([
                            'kyc_status' => 'approved',
                        ]);

                        // TODO: Send notification to user
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Rejeter')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (KycDocument $record): bool => $record->verification_status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Raison du rejet')
                            ->required()
                            ->rows(4)
                            ->placeholder('Expliquez la raison du rejet du document KYC'),
                    ])
                    ->action(function (KycDocument $record, array $data): void {
                        $record->update([
                            'verification_status' => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                            'verified_by' => auth()->id(),
                            'verified_at' => now(),
                        ]);

                        // Update user KYC status
                        $record->user->update([
                            'kyc_status' => 'rejected',
                        ]);

                        // TODO: Send notification to user
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
            'index' => Pages\ListKycDocuments::route('/'),
            'create' => Pages\CreateKycDocument::route('/create'),
            'edit' => Pages\EditKycDocument::route('/{record}/edit'),
        ];
    }
}
