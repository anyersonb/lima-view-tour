<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TourResource\Pages;
use App\Models\Tour;
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
                                    ->helperText('Se genera automáticamente del título si lo dejas vacío.')
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
                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('price')->required()->numeric()->prefix('$')->label('Precio actual'),
                                    Forms\Components\TextInput::make('price_before')->numeric()->prefix('$')->label('Precio antes'),
                                    Forms\Components\TextInput::make('currency')->required()->maxLength(3)->default('USD'),
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
                                    Forms\Components\TextInput::make('rating')->numeric()->step(0.1)->default(4.8)->label('Calificación'),
                                    Forms\Components\TextInput::make('reviews_count')->numeric()->default(0)->label('# reseñas'),
                                    Forms\Components\TextInput::make('order')->numeric()->default(0)->label('Orden'),
                                ]),
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\Toggle::make('is_published')->label('Publicado')->default(true),
                                    Forms\Components\Toggle::make('is_featured')->label('Destacado'),
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
                            ]),

                        Tabs\Tab::make('Imágenes')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Forms\Components\FileUpload::make('cover_image')
                                    ->image()
                                    ->disk('public')
                                    ->directory('tours/covers')
                                    ->imageEditor()
                                    ->label('Imagen de portada'),
                                Forms\Components\FileUpload::make('gallery')
                                    ->multiple()
                                    ->image()
                                    ->disk('public')
                                    ->directory('tours/gallery')
                                    ->reorderable()
                                    ->panelLayout('grid')
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
                                    ->helperText('Imagen Open Graph (1200x630px)')
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
