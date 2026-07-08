<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TourResource\Pages;
use App\Models\Tour;
use App\Support\ImageOptimizer;
use App\Support\ImagePath;
use Filament\Forms;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TourResource extends Resource
{
    protected static ?string $model = Tour::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-americas';
    protected static ?string $navigationGroup = 'Catálogo';
    protected static ?string $navigationLabel = 'Tours';
    protected static ?string $modelLabel = 'Tour';
    protected static ?string $pluralModelLabel = 'Tours';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Tour')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('General')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Select::make('region_id')
                                        ->relationship('region', 'name_es')
                                        ->searchable()->preload()
                                        ->label('Región'),
                                    Forms\Components\Select::make('category_id')
                                        ->relationship('category', 'name_es')
                                        ->searchable()->preload()
                                        ->label('Categoría'),
                                ]),
                                Forms\Components\TextInput::make('slug')
                                    ->label('URL del tour (slug)')
                                    ->helperText('Es la dirección pública del tour. Se genera automáticamente del título al crear y NO se puede editar después: cambiarla rompería los enlaces ya compartidos e indexados.')
                                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                                    ->dehydrated(fn (string $operation): bool => $operation === 'create')
                                    ->maxLength(255),
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('duration')->label('Duración')->placeholder('Full Day'),
                                    Forms\Components\TextInput::make('language')->label('Idiomas')->default('Español / Inglés'),
                                    Forms\Components\TextInput::make('group_type')->label('Tipo de grupo')->default('Grupal'),
                                ]),
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('departure_time')->label('Hora salida')->placeholder('05:00 AM'),
                                    Forms\Components\TextInput::make('return_time')->label('Hora retorno')->placeholder('10:30 PM'),
                                    Forms\Components\TextInput::make('max_capacity')->numeric()->label('Capacidad máxima'),
                                ]),
                                Forms\Components\Section::make('Precios y oferta')
                                    ->description('Configura el precio y, opcionalmente, activa una oferta especial.')
                                    ->icon('heroicon-o-tag')
                                    ->schema([
                                        Forms\Components\Grid::make(3)->schema([
                                            Forms\Components\TextInput::make('price')
                                                ->required()
                                                ->numeric()
                                                ->prefix('$')
                                                ->label('Precio actual (AHORA)')
                                                ->helperText('Es el precio que paga el cliente.')
                                                ->reactive(),
                                            Forms\Components\TextInput::make('price_before')
                                                ->numeric()
                                                ->prefix('$')
                                                ->label('Precio antes (oferta)')
                                                ->helperText('Escribe aquí el precio original (más alto que el actual) para activar la OFERTA ESPECIAL con su % de descuento automático. Déjalo VACÍO si el tour NO tiene oferta.')
                                                ->reactive(),
                                            Forms\Components\TextInput::make('currency')
                                                ->required()
                                                ->maxLength(3)
                                                ->default('USD')
                                                ->label('Moneda'),
                                        ]),
                                        Forms\Components\Placeholder::make('discount_preview')
                                            ->label('Vista previa del descuento')
                                            ->content(function (Forms\Get $get): string {
                                                $price = (float) $get('price');
                                                $priceBefore = (float) $get('price_before');

                                                if ($priceBefore <= 0 || $price <= 0) {
                                                    return '— Sin oferta activa (precio antes vacío).';
                                                }

                                                if ($priceBefore <= $price) {
                                                    return '⚠ Sin oferta: el precio antes debe ser MAYOR que el precio actual.';
                                                }

                                                $discount = round((1 - $price / $priceBefore) * 100);

                                                return "✔ OFERTA ESPECIAL -{$discount}% activa — el card mostrará \"ANTES US\${$priceBefore}\" tachado y \"AHORA US\${$price}\".";
                                            }),
                                    ]),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('badge_text')->label('Texto del badge')->placeholder('CUPOS LIMITADOS'),
                                    Forms\Components\Select::make('badge_type')->label('Tipo de badge')->options([
                                        'warn' => 'Naranja (advertencia)',
                                        'error' => 'Rojo (urgencia)',
                                        'success' => 'Verde (éxito)',
                                    ]),
                                ]),
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('rating')->numeric()->step(0.1)->minValue(1)->maxValue(5)->default(4.8)->label('Calificación')->helperText('Entre 1 y 5.'),
                                    Forms\Components\TextInput::make('reviews_count')->numeric()->default(0)->label('# reseñas'),
                                    Forms\Components\TextInput::make('order')->numeric()->default(0)->label('Orden'),
                                    Forms\Components\TextInput::make('featured_order')
                                        ->numeric()
                                        ->minValue(1)
                                        ->nullable()
                                        ->label('Orden en "Más Comprados"')
                                        ->helperText('Posición manual en la sección "Tours más Comprados" del home (1 = primero). Vacío = se ordena solo por número de reservas. Aplica a tours con "Destacado" activo.'),
                                ]),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Toggle::make('is_published')->label('Publicado')->default(true),
                                    Forms\Components\Toggle::make('is_featured')->label('Destacado'),
                                    Forms\Components\Toggle::make('show_best_seller')
                                        ->label('Badge "BEST SELLER"')
                                        ->helperText('Muestra u oculta el escudo BEST SELLER en las tarjetas y el detalle del tour.')
                                        ->default(true),
                                    Forms\Components\Toggle::make('show_offer_badge')
                                        ->label('Badge "Oferta especial"')
                                        ->helperText('Muestra u oculta la pastilla OFERTA ESPECIAL −N% (solo aplica si hay precio antes).')
                                        ->default(true),
                                ]),
                            ]),

                        Tabs\Tab::make('Español')
                            ->icon('heroicon-o-language')
                            ->schema([
                                Forms\Components\TextInput::make('title_es')->required()->maxLength(255)->label('Título'),
                                Forms\Components\TextInput::make('subtitle_es')->maxLength(255)->label('Subtítulo'),
                                Forms\Components\Textarea::make('description_es')->rows(6)->label('Descripción'),
                                Forms\Components\Repeater::make('itinerary_es')->label('Itinerario')->schema([
                                    Forms\Components\TextInput::make('time')->label('Hora'),
                                    Forms\Components\TextInput::make('title')->label('Título'),
                                    Forms\Components\Textarea::make('description')->label('Descripción')->rows(2),
                                ])->columns(3)->collapsible()->reorderable(),
                                Forms\Components\TagsInput::make('includes_es')->label('Incluye')->placeholder('Agregar item'),
                                Forms\Components\TagsInput::make('excludes_es')->label('No incluye')->placeholder('Agregar item'),
                                Forms\Components\Textarea::make('recommendations_es')->rows(3)->label('Recomendaciones'),
                                Forms\Components\Textarea::make('notes_es')->rows(3)->label('Notas importantes'),
                                Forms\Components\Repeater::make('faqs_es')->label('Preguntas frecuentes')->schema([
                                    Forms\Components\TextInput::make('question')->label('Pregunta')->required(),
                                    Forms\Components\Textarea::make('answer')->label('Respuesta')->rows(3)->required(),
                                ])->collapsible()->reorderable()->defaultItems(0)
                                  ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                                  ->helperText('Se muestran como acordeón en la página del tour. Déjalo vacío si el tour no lleva FAQs.'),
                            ]),

                        Tabs\Tab::make('English')
                            ->icon('heroicon-o-language')
                            ->schema([
                                Forms\Components\TextInput::make('title_en')->maxLength(255)->label('Title'),
                                Forms\Components\TextInput::make('subtitle_en')->maxLength(255)->label('Subtitle'),
                                Forms\Components\Textarea::make('description_en')->rows(6)->label('Description'),
                                Forms\Components\Repeater::make('itinerary_en')->label('Itinerary')->schema([
                                    Forms\Components\TextInput::make('time')->label('Time'),
                                    Forms\Components\TextInput::make('title')->label('Title'),
                                    Forms\Components\Textarea::make('description')->label('Description')->rows(2),
                                ])->columns(3)->collapsible(),
                                Forms\Components\TagsInput::make('includes_en')->label('Includes'),
                                Forms\Components\TagsInput::make('excludes_en')->label('Excludes'),
                                Forms\Components\Textarea::make('recommendations_en')->rows(3)->label('Recommendations'),
                                Forms\Components\Textarea::make('notes_en')->rows(3)->label('Important notes'),
                                Forms\Components\Repeater::make('faqs_en')->label('Frequently asked questions')->schema([
                                    Forms\Components\TextInput::make('question')->label('Question')->required(),
                                    Forms\Components\Textarea::make('answer')->label('Answer')->rows(3)->required(),
                                ])->collapsible()->reorderable()->defaultItems(0)
                                  ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                                  ->helperText('Shown as an accordion on the tour page. Falls back to Spanish if empty.'),
                            ]),

                        Tabs\Tab::make('Português')
                            ->icon('heroicon-o-language')
                            ->schema([
                                Forms\Components\TextInput::make('title_pt')->maxLength(255)->label('Título'),
                                Forms\Components\TextInput::make('subtitle_pt')->maxLength(255)->label('Subtítulo'),
                                Forms\Components\Textarea::make('description_pt')->rows(6)->label('Descrição'),
                                Forms\Components\Repeater::make('itinerary_pt')->label('Roteiro')->schema([
                                    Forms\Components\TextInput::make('time')->label('Hora'),
                                    Forms\Components\TextInput::make('title')->label('Título'),
                                    Forms\Components\Textarea::make('description')->label('Descrição')->rows(2),
                                ])->columns(3)->collapsible(),
                                Forms\Components\TagsInput::make('includes_pt')->label('Inclui')->placeholder('Adicionar item'),
                                Forms\Components\TagsInput::make('excludes_pt')->label('Não inclui')->placeholder('Adicionar item'),
                                Forms\Components\Textarea::make('recommendations_pt')->rows(3)->label('Recomendações'),
                                Forms\Components\Textarea::make('notes_pt')->rows(3)->label('Notas importantes'),
                                Forms\Components\Repeater::make('faqs_pt')->label('Perguntas frequentes')->schema([
                                    Forms\Components\TextInput::make('question')->label('Pergunta')->required(),
                                    Forms\Components\Textarea::make('answer')->label('Resposta')->rows(3)->required(),
                                ])->collapsible()->reorderable()->defaultItems(0)
                                  ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                                  ->helperText('Exibido como acordeão na página do tour. Se vazio, usa o espanhol.'),
                            ]),

                        Tabs\Tab::make('Imágenes')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Forms\Components\FileUpload::make('cover_image')
                                    ->image()
                                    ->disk('public')
                                    ->directory('tours/covers')
                                    ->imageEditor()
                                    ->saveUploadedFileUsing(ImageOptimizer::saver('tours/covers', 1600, deletePrevious: true))
                                    ->helperText('Se optimiza automáticamente a WebP (máx. 1600px de ancho).')
                                    ->label('Imagen de portada'),
                                Forms\Components\FileUpload::make('gallery')
                                    ->multiple()
                                    ->image()
                                    ->disk('public')
                                    ->directory('tours/gallery')
                                    ->reorderable()
                                    ->panelLayout('grid')
                                    ->saveUploadedFileUsing(ImageOptimizer::saver('tours/gallery', 1920))
                                    ->helperText('Cada imagen se optimiza a WebP (máx. 1920px de ancho).')
                                    ->label('Galería'),
                            ]),

                        Tabs\Tab::make('SEO')
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Forms\Components\TextInput::make('seo_title')
                                    ->maxLength(70)
                                    ->helperText('Recomendado: 50-60 caracteres')
                                    ->label('Title (SEO)'),
                                Forms\Components\Textarea::make('seo_description')
                                    ->maxLength(160)
                                    ->rows(3)
                                    ->helperText('Recomendado: 150-160 caracteres')
                                    ->label('Meta description'),
                                Forms\Components\TagsInput::make('seo_keywords')->label('Keywords'),
                                Forms\Components\FileUpload::make('seo_image')
                                    ->image()
                                    ->disk('public')
                                    ->directory('tours/seo')
                                    ->imageEditor()
                                    ->saveUploadedFileUsing(ImageOptimizer::saver('tours/seo', 1200, deletePrevious: true))
                                    ->helperText('Imagen Open Graph — se optimiza a WebP (máx. 1200px).')
                                    ->label('OG Image'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')
                    ->getStateUsing(fn ($record) => ImagePath::url($record->cover_image))
                    ->square()
                    ->size(60)
                    ->label(''),
                Tables\Columns\TextColumn::make('title_es')
                    ->searchable()->limit(40)
                    ->label('Título'),
                Tables\Columns\TextColumn::make('region.name_es')
                    ->badge()->color('info')
                    ->label('Región'),
                Tables\Columns\TextColumn::make('category.name_es')
                    ->badge()->color('gray')
                    ->label('Categoría'),
                Tables\Columns\TextColumn::make('price')
                    ->money('USD')->sortable()
                    ->label('Precio'),
                Tables\Columns\IconColumn::make('has_offer')
                    ->label('Oferta')
                    ->getStateUsing(fn ($record): bool => filled($record->price_before) && (float) $record->price_before > (float) $record->price)
                    ->boolean()
                    ->trueIcon('heroicon-o-tag')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn ($record): string => filled($record->price_before) && (float) $record->price_before > (float) $record->price
                        ? 'OFERTA ESPECIAL -' . round((1 - (float) $record->price / (float) $record->price_before) * 100) . '%'
                        : 'Sin oferta'
                    ),
                Tables\Columns\TextColumn::make('rating')
                    ->numeric(decimalPlaces: 1)->sortable()
                    ->label('★'),
                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->label('Destacado'),
                Tables\Columns\IconColumn::make('is_published')
                    ->boolean()
                    ->label('Publicado'),
                Tables\Columns\TextColumn::make('order')
                    ->sortable()
                    ->label('#'),
                Tables\Columns\TextColumn::make('featured_order')
                    ->sortable()
                    ->label('# Más Comprados')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('region_id')
                    ->relationship('region', 'name_es')
                    ->label('Región'),
                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name_es')
                    ->label('Categoría'),
                Tables\Filters\TernaryFilter::make('is_published')->label('Publicado'),
                Tables\Filters\TernaryFilter::make('is_featured')->label('Destacado'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->reorderable('order');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTours::route('/'),
            'create' => Pages\CreateTour::route('/create'),
            'edit' => Pages\EditTour::route('/{record}/edit'),
        ];
    }
}
