<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use App\Support\ImagePath;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Contenido';
    protected static ?string $navigationLabel = 'Páginas';
    protected static ?string $modelLabel = 'Página';
    protected static ?string $pluralModelLabel = 'Páginas';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('page_tabs')
                    ->columnSpanFull()
                    ->tabs([

                        // ── Tab 1: General ───────────────────────────────────
                        Forms\Components\Tabs\Tab::make('General')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Identificador único en la URL (ej: contacto, nosotros).')
                                    ->columnSpanFull(),

                                Forms\Components\Toggle::make('is_published')
                                    ->label('Publicada')
                                    ->default(true),

                                Forms\Components\Toggle::make('show_in_sitemap')
                                    ->label('Incluir en sitemap')
                                    ->default(true),

                                Forms\Components\TextInput::make('sitemap_priority')
                                    ->label('Prioridad sitemap')
                                    ->default(0.5)
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('sitemap_changefreq')
                                    ->label('Frecuencia sitemap')
                                    ->default('monthly')
                                    ->maxLength(255),
                            ])
                            ->columns(2),

                        // ── Tab 2: Títulos y contenido ───────────────────────
                        Forms\Components\Tabs\Tab::make('Títulos y contenido')
                            ->icon('heroicon-o-language')
                            ->schema([
                                Forms\Components\Section::make('Español')
                                    ->columns(1)
                                    ->schema([
                                        Forms\Components\TextInput::make('title_es')
                                            ->label('Título')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('content_es')
                                            ->label('Contenido')
                                            ->rows(4),
                                    ]),

                                Forms\Components\Section::make('English')
                                    ->columns(1)
                                    ->schema([
                                        Forms\Components\TextInput::make('title_en')
                                            ->label('Title')
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('content_en')
                                            ->label('Content')
                                            ->rows(4),
                                    ]),

                                Forms\Components\Section::make('Português')
                                    ->columns(1)
                                    ->schema([
                                        Forms\Components\TextInput::make('title_pt')
                                            ->label('Título')
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('content_pt')
                                            ->label('Conteúdo')
                                            ->rows(4),
                                    ]),
                            ]),

                        // ── Tab 3: SEO ────────────────────────────────────────
                        Forms\Components\Tabs\Tab::make('SEO')
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Forms\Components\TextInput::make('seo_title')
                                    ->label('Meta título')
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('seo_description')
                                    ->label('Meta descripción')
                                    ->maxLength(320)
                                    ->rows(3)
                                    ->columnSpanFull(),

                                Forms\Components\FileUpload::make('hero_image')
                                    ->label('Imagen hero (global)')
                                    ->disk('media')
                                    ->directory('paginas')
                                    ->image()
                                    ->imageEditor()
                                    ->maxSize(4096),

                                Forms\Components\FileUpload::make('seo_image')
                                    ->label('Imagen OG / Twitter Card')
                                    ->disk('media')
                                    ->directory('paginas')
                                    ->image()
                                    ->maxSize(2048),
                            ])
                            ->columns(2),

                        // ── Tab 4: Contenido de la página (Contacto / Nosotros) ──
                        Forms\Components\Tabs\Tab::make('Contenido de la página')
                            ->icon('heroicon-o-photo')
                            ->visible(fn (Forms\Get $get): bool => in_array($get('slug'), ['contacto', 'nosotros']))
                            ->schema([

                                // ── SECCIÓN CONTACTO ─────────────────────────
                                Forms\Components\Section::make('Imágenes — Contacto')
                                    ->description('Imágenes visibles en la página /contacto')
                                    ->visible(fn (Forms\Get $get): bool => $get('slug') === 'contacto')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\FileUpload::make('blocks.img_hero')
                                            ->label('Hero (fondo superior)')
                                            ->helperText('Reemplaza Rectangle 19216.jpg del banner hero')
                                            ->disk('media')
                                            ->directory('paginas/contacto')
                                            ->image()
                                            ->imageEditor()
                                            ->maxSize(4096)
                                            ->columnSpanFull(),

                                        Forms\Components\FileUpload::make('blocks.img_collage_1')
                                            ->label('Collage — Superior izquierda')
                                            ->helperText('Reemplaza Rectangle 19210.jpg')
                                            ->disk('media')
                                            ->directory('paginas/contacto')
                                            ->image()
                                            ->imageEditor()
                                            ->maxSize(4096),

                                        Forms\Components\FileUpload::make('blocks.img_collage_2')
                                            ->label('Collage — Superior derecha')
                                            ->helperText('Reemplaza Rectangle 19211.jpg (arriba)')
                                            ->disk('media')
                                            ->directory('paginas/contacto')
                                            ->image()
                                            ->imageEditor()
                                            ->maxSize(4096),

                                        Forms\Components\FileUpload::make('blocks.img_collage_3')
                                            ->label('Collage — Inferior izquierda (personas)')
                                            ->helperText('Reemplaza personas.png')
                                            ->disk('media')
                                            ->directory('paginas/contacto')
                                            ->image()
                                            ->imageEditor()
                                            ->maxSize(4096),

                                        Forms\Components\FileUpload::make('blocks.img_collage_4')
                                            ->label('Collage — Inferior derecha')
                                            ->helperText('Reemplaza Rectangle 19212.jpg')
                                            ->disk('media')
                                            ->directory('paginas/contacto')
                                            ->image()
                                            ->imageEditor()
                                            ->maxSize(4096),
                                    ]),

                                Forms\Components\Section::make('Textos hero — Contacto')
                                    ->description('Eyebrow, título H1 y párrafo lead del hero')
                                    ->visible(fn (Forms\Get $get): bool => $get('slug') === 'contacto')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('blocks.hero_eyebrow_es')
                                            ->label('Eyebrow (ES)')
                                            ->placeholder('RESOLVEMOS TUS DUDAS')
                                            ->maxLength(120),
                                        Forms\Components\TextInput::make('blocks.hero_eyebrow_en')
                                            ->label('Eyebrow (EN)')
                                            ->placeholder('WE SOLVE YOUR QUESTIONS')
                                            ->maxLength(120),
                                        Forms\Components\TextInput::make('blocks.hero_eyebrow_pt')
                                            ->label('Eyebrow (PT)')
                                            ->placeholder('RESOLVEMOS SUAS DÚVIDAS')
                                            ->maxLength(120),

                                        Forms\Components\TextInput::make('blocks.hero_title_es')
                                            ->label('Título H1 (ES)')
                                            ->placeholder('Contáctanos')
                                            ->maxLength(200),
                                        Forms\Components\TextInput::make('blocks.hero_title_en')
                                            ->label('Title H1 (EN)')
                                            ->placeholder('Contact Us')
                                            ->maxLength(200),
                                        Forms\Components\TextInput::make('blocks.hero_title_pt')
                                            ->label('Título H1 (PT)')
                                            ->placeholder('Fale Conosco')
                                            ->maxLength(200),

                                        Forms\Components\Textarea::make('blocks.hero_lead_es')
                                            ->label('Lead párrafo (ES)')
                                            ->rows(3)
                                            ->maxLength(400),
                                        Forms\Components\Textarea::make('blocks.hero_lead_en')
                                            ->label('Lead paragraph (EN)')
                                            ->rows(3)
                                            ->maxLength(400),
                                        Forms\Components\Textarea::make('blocks.hero_lead_pt')
                                            ->label('Lead parágrafo (PT)')
                                            ->rows(3)
                                            ->maxLength(400),
                                    ]),

                                // ── SECCIÓN NOSOTROS ─────────────────────────
                                Forms\Components\Section::make('Imágenes — Nosotros')
                                    ->description('Imágenes visibles en la página /nosotros')
                                    ->visible(fn (Forms\Get $get): bool => $get('slug') === 'nosotros')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\FileUpload::make('blocks.img_hero')
                                            ->label('Hero (fondo superior)')
                                            ->helperText('Reemplaza Rectangle 19215.jpg')
                                            ->disk('media')
                                            ->directory('paginas/nosotros')
                                            ->image()
                                            ->imageEditor()
                                            ->maxSize(4096)
                                            ->columnSpanFull(),

                                        Forms\Components\FileUpload::make('blocks.img_grid1')
                                            ->label('Grid col-1 fila-1 (Lima nocturna)')
                                            ->helperText('Reemplaza Rectangle 19216.jpg')
                                            ->disk('media')
                                            ->directory('paginas/nosotros')
                                            ->image()
                                            ->imageEditor()
                                            ->maxSize(4096),

                                        Forms\Components\FileUpload::make('blocks.img_grid2')
                                            ->label('Grid col-2 fila-1 (Machu Picchu)')
                                            ->helperText('Reemplaza Rectangle 19217.jpg')
                                            ->disk('media')
                                            ->directory('paginas/nosotros')
                                            ->image()
                                            ->imageEditor()
                                            ->maxSize(4096),

                                        Forms\Components\FileUpload::make('blocks.img_grid3')
                                            ->label('Grid col-1 fila-2 (Cusco colonial)')
                                            ->helperText('Reemplaza Rectangle 19218.jpg')
                                            ->disk('media')
                                            ->directory('paginas/nosotros')
                                            ->image()
                                            ->imageEditor()
                                            ->maxSize(4096),

                                        Forms\Components\FileUpload::make('blocks.img_grid4')
                                            ->label('Grid col-2 fila-2 (Huacachina oasis)')
                                            ->helperText('Reemplaza Rectangle 19219.jpg')
                                            ->disk('media')
                                            ->directory('paginas/nosotros')
                                            ->image()
                                            ->imageEditor()
                                            ->maxSize(4096),

                                        Forms\Components\FileUpload::make('blocks.img_banner_cta')
                                            ->label('Banner CTA "Somos Lima View Tours"')
                                            ->helperText('Reemplaza Rectangle 19214.jpg')
                                            ->disk('media')
                                            ->directory('paginas/nosotros')
                                            ->image()
                                            ->imageEditor()
                                            ->maxSize(4096),

                                        Forms\Components\FileUpload::make('blocks.img_testimonios')
                                            ->label('Fondo tarjeta testimonios')
                                            ->helperText('Reemplaza Rectangle 19211.jpg (sección testimonios)')
                                            ->disk('media')
                                            ->directory('paginas/nosotros')
                                            ->image()
                                            ->imageEditor()
                                            ->maxSize(4096),
                                    ]),

                                Forms\Components\Section::make('Textos hero — Nosotros')
                                    ->description('Eyebrow, título H1 y párrafo lead del hero')
                                    ->visible(fn (Forms\Get $get): bool => $get('slug') === 'nosotros')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('blocks.hero_eyebrow_es')
                                            ->label('Eyebrow (ES)')
                                            ->placeholder('SOBRE NOSOTROS')
                                            ->maxLength(120),
                                        Forms\Components\TextInput::make('blocks.hero_eyebrow_en')
                                            ->label('Eyebrow (EN)')
                                            ->placeholder('ABOUT US')
                                            ->maxLength(120),
                                        Forms\Components\TextInput::make('blocks.hero_eyebrow_pt')
                                            ->label('Eyebrow (PT)')
                                            ->placeholder('SOBRE NÓS')
                                            ->maxLength(120),

                                        Forms\Components\TextInput::make('blocks.hero_title_es')
                                            ->label('Título H1 (ES)')
                                            ->placeholder('Somos planificadores profesionales para tus vacaciones')
                                            ->maxLength(200),
                                        Forms\Components\TextInput::make('blocks.hero_title_en')
                                            ->label('Title H1 (EN)')
                                            ->placeholder('We are professional vacation planners')
                                            ->maxLength(200),
                                        Forms\Components\TextInput::make('blocks.hero_title_pt')
                                            ->label('Título H1 (PT)')
                                            ->placeholder('Somos planejadores profissionais de férias')
                                            ->maxLength(200),

                                        Forms\Components\Textarea::make('blocks.hero_lead_es')
                                            ->label('Lead párrafo (ES)')
                                            ->rows(3)
                                            ->maxLength(400),
                                        Forms\Components\Textarea::make('blocks.hero_lead_en')
                                            ->label('Lead paragraph (EN)')
                                            ->rows(3)
                                            ->maxLength(400),
                                        Forms\Components\Textarea::make('blocks.hero_lead_pt')
                                            ->label('Lead parágrafo (PT)')
                                            ->rows(3)
                                            ->maxLength(400),
                                    ]),

                                Forms\Components\Section::make('Textos sección "¿Por qué reservar?"')
                                    ->description('Intro de la sección con grid de imágenes')
                                    ->visible(fn (Forms\Get $get): bool => $get('slug') === 'nosotros')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\Textarea::make('blocks.why_intro_es')
                                            ->label('Párrafo intro (ES)')
                                            ->rows(3)
                                            ->maxLength(600),
                                        Forms\Components\Textarea::make('blocks.why_intro_en')
                                            ->label('Intro paragraph (EN)')
                                            ->rows(3)
                                            ->maxLength(600),
                                        Forms\Components\Textarea::make('blocks.why_intro_pt')
                                            ->label('Parágrafo intro (PT)')
                                            ->rows(3)
                                            ->maxLength(600),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title_es')
                    ->label('Título ES')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title_en')
                    ->label('Title EN')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('hero_image')
                    ->getStateUsing(fn ($record) => ImagePath::url($record->hero_image)),
                Tables\Columns\TextColumn::make('seo_title')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_published')
                    ->label('Publicada')
                    ->boolean(),
                Tables\Columns\IconColumn::make('show_in_sitemap')
                    ->label('Sitemap')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit'   => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
