<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferResource\Pages;
use App\Filament\Resources\OfferResource\RelationManagers;
use App\Models\Offer;
use App\Support\ImagePath;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationGroup = 'Marketing';
    protected static ?string $navigationLabel = 'Ofertas';
    protected static ?string $modelLabel = 'Oferta';
    protected static ?string $pluralModelLabel = 'Ofertas';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title_es')
                    ->required()
                    ->maxLength(255)
                    ->label('Título (Español)'),
                Forms\Components\TextInput::make('title_en')
                    ->maxLength(255)
                    ->label('Title (English)'),
                Forms\Components\TextInput::make('title_pt')
                    ->maxLength(255)
                    ->label('Título (Português)'),
                Forms\Components\Textarea::make('description_es')
                    ->label('Descripción (Español)')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description_en')
                    ->label('Description (English)')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description_pt')
                    ->label('Descrição (Português)')
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('image')
                    ->image(),
                Forms\Components\TextInput::make('price')
                    ->numeric()
                    ->prefix('$'),
                Forms\Components\TextInput::make('cta_label_es')
                    ->required()
                    ->maxLength(255)
                    ->default('Leer más')
                    ->label('CTA (Español)'),
                Forms\Components\TextInput::make('cta_label_en')
                    ->maxLength(255)
                    ->label('CTA (English)'),
                Forms\Components\TextInput::make('cta_label_pt')
                    ->maxLength(255)
                    ->label('CTA (Português)'),
                Forms\Components\TextInput::make('cta_url')
                    ->maxLength(255),
                Forms\Components\Select::make('tour_id')
                    ->relationship('tour', 'id'),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
                Forms\Components\TextInput::make('order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\DateTimePicker::make('valid_until'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title_es')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title_en')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('image')
                    ->getStateUsing(fn ($record) => ImagePath::url($record->image)),
                Tables\Columns\TextColumn::make('price')
                    ->money()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cta_label_es')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cta_label_en')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cta_url')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tour.id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('order')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('valid_until')
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
            'index' => Pages\ListOffers::route('/'),
            'create' => Pages\CreateOffer::route('/create'),
            'edit' => Pages\EditOffer::route('/{record}/edit'),
        ];
    }
}
