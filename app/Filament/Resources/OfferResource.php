<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OfferResource\Pages;
use App\Filament\Resources\OfferResource\RelationManagers;
use App\Models\Offer;
use App\Support\ImageOptimizer;
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
                    ->label('Título (Español)')
                    ->helperText('Es el texto de la banda de promoción del home. Escribe solo ofertas reales.'),
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
                Forms\Components\FileUpload::make('image')->label('Imagen')
                    ->fetchFileInformation(false)
                    ->image()
                    // Hallazgo de seguridad #5 (2026-09-30): nombre en disco
                    // desde el MIME detectado, nunca la extensión del cliente.
                    ->getUploadedFileNameForStorageUsing(ImageOptimizer::safeImageNamer()),
                Forms\Components\TextInput::make('price')->label('Precio')
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
                    ->label('Enlace del botón')
                    ->maxLength(255)
                    ->helperText('Ej.: /es/tours o https://... Si lo dejas vacío y eliges un tour, el botón lleva a ese tour.')
                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                        if (filled($value) && ! preg_match('#^(https?://|/(?!/))#i', (string) $value)) {
                            $fail('El enlace debe empezar con https:// o con / (ruta interna).');
                        }
                    }),
                Forms\Components\Select::make('tour_id')
                    ->label('Tour de la oferta (opcional)')
                    ->relationship('tour', 'title_es')
                    ->searchable()
                    ->preload()
                    ->helperText('Si eliges un tour que luego se despublica, la oferta deja de mostrarse sola.'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Oferta activa')
                    ->helperText('Apagada = no se muestra. Si no hay ninguna oferta activa y vigente, la banda de promoción del home no aparece.')
                    ->required(),
                Forms\Components\TextInput::make('order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\DateTimePicker::make('valid_until')
                    ->label('Válida hasta')
                    ->helperText('Después de esta fecha y hora la oferta se oculta sola. Vacío = sin fecha de fin.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title_es')
                    ->label('Título (ES)')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title_en')
                    ->label('Título (EN)')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('image')
                    ->label('Imagen')
                    ->getStateUsing(fn ($record) => ImagePath::url($record->image)),
                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->money()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cta_label_es')
                    ->label('Texto del botón (ES)')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cta_label_en')
                    ->label('Texto del botón (EN)')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cta_url')
                    ->label('Enlace del botón')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tour.id')
                    ->label('Tour (ID)')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('order')
                    ->label('Orden')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Válida hasta')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado el')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado el')
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
