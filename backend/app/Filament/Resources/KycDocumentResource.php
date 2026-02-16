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

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Forms\Components\TextInput::make('document_type')
                    ->required(),
                Forms\Components\TextInput::make('document_path')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('selfie_path')
                    ->maxLength(255),
                Forms\Components\TextInput::make('verification_status')
                    ->required(),
                Forms\Components\Textarea::make('rejection_reason')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('verified_by')
                    ->numeric(),
                Forms\Components\DateTimePicker::make('verified_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('document_type'),
                Tables\Columns\TextColumn::make('document_path')
                    ->searchable(),
                Tables\Columns\TextColumn::make('selfie_path')
                    ->searchable(),
                Tables\Columns\TextColumn::make('verification_status'),
                Tables\Columns\TextColumn::make('verified_by')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('verified_at')
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
            'index' => Pages\ListKycDocuments::route('/'),
            'create' => Pages\CreateKycDocument::route('/create'),
            'edit' => Pages\EditKycDocument::route('/{record}/edit'),
        ];
    }
}
