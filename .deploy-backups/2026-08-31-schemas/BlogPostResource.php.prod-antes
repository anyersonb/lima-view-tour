<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasEditableSlugField;
use App\Filament\Concerns\HasLocalizedSeoFields;
use App\Filament\Resources\BlogPostResource\Pages;
use App\Models\BlogPost;
use Filament\Forms;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BlogPostResource extends Resource
{
    use HasLocalizedSeoFields, HasEditableSlugField;

    protected static ?string $model = BlogPost::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';
    protected static ?string $navigationGroup = 'Contenido';
    protected static ?string $navigationLabel = 'Blog';
    protected static ?string $modelLabel = 'Artículo';
    protected static ?string $pluralModelLabel = 'Artículos';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('BlogPost')
                    ->columnSpanFull()
                    ->tabs([

                        // ── Spanish content ──────────────────────────────
                        Tabs\Tab::make('Español')
                            ->icon('heroicon-o-language')
                            ->schema([
                                Forms\Components\TextInput::make('title_es')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('Título'),
                                Forms\Components\Textarea::make('excerpt_es')
                                    ->required()
                                    ->rows(3)
                                    ->label('Extracto'),
                                Forms\Components\RichEditor::make('body_es')
                                    ->required()
                                    ->fileAttachmentsDisk('public')
                                    ->fileAttachmentsDirectory('blog/attachments')
                                    ->label('Cuerpo del artículo'),

                                Forms\Components\Section::make('SEO — Español')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->collapsible()
                                    ->schema([
                                        static::slugField(BlogPost::class, 'es', 'slug', 'Slug (URL) — Español')
                                            ->unique(ignoreRecord: true),
                                        Forms\Components\TextInput::make('meta_title_es')
                                            ->maxLength(70)
                                            ->live()
                                            ->helperText(fn (Forms\Get $get): string => static::seoCharHelper($get('meta_title_es'), 50, 60))
                                            ->label('Meta title (Español)'),
                                        Forms\Components\Textarea::make('meta_description_es')
                                            ->maxLength(160)
                                            ->rows(3)
                                            ->live()
                                            ->helperText(fn (Forms\Get $get): string => static::seoCharHelper($get('meta_description_es'), 150, 160))
                                            ->label('Meta description (Español)'),
                                        Forms\Components\Textarea::make('schema_jsonld_es')
                                            ->label('Datos estructurados (JSON-LD) — Español')
                                            ->rows(6)
                                            ->helperText('Opcional. Si lo completas, REEMPLAZA el JSON-LD automático (BlogPosting) de esta entrada en este idioma. Debe ser JSON válido.')
                                            ->rules([static::seoJsonLdRule()]),
                                    ]),
                            ]),

                        // ── English content ───────────────────────────────
                        Tabs\Tab::make('English')
                            ->icon('heroicon-o-language')
                            ->schema([
                                Forms\Components\TextInput::make('title_en')
                                    ->maxLength(255)
                                    ->label('Title'),
                                Forms\Components\Textarea::make('excerpt_en')
                                    ->rows(3)
                                    ->label('Excerpt'),
                                Forms\Components\RichEditor::make('body_en')
                                    ->fileAttachmentsDisk('public')
                                    ->fileAttachmentsDirectory('blog/attachments')
                                    ->label('Body'),

                                Forms\Components\Section::make('SEO — English')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->collapsible()
                                    ->schema([
                                        static::slugField(BlogPost::class, 'en', 'slug_en', 'Slug (URL) — English', titleField: 'title_en')
                                            ->unique(ignoreRecord: true),
                                        Forms\Components\TextInput::make('meta_title_en')
                                            ->maxLength(70)
                                            ->live()
                                            ->helperText(fn (Forms\Get $get): string => static::seoCharHelper($get('meta_title_en'), 50, 60))
                                            ->label('Meta title (English)'),
                                        Forms\Components\Textarea::make('meta_description_en')
                                            ->maxLength(160)
                                            ->rows(3)
                                            ->live()
                                            ->helperText(fn (Forms\Get $get): string => static::seoCharHelper($get('meta_description_en'), 150, 160))
                                            ->label('Meta description (English)'),
                                        Forms\Components\Textarea::make('schema_jsonld_en')
                                            ->label('Structured data (JSON-LD) — English')
                                            ->rows(6)
                                            ->helperText('Optional. Falls back to Spanish if empty. Must be valid JSON.')
                                            ->rules([static::seoJsonLdRule()]),
                                    ]),
                            ]),

                        // ── Portuguese content ────────────────────────────
                        Tabs\Tab::make('Português')
                            ->icon('heroicon-o-language')
                            ->schema([
                                Forms\Components\TextInput::make('title_pt')
                                    ->maxLength(255)
                                    ->label('Título'),
                                Forms\Components\Textarea::make('excerpt_pt')
                                    ->rows(3)
                                    ->label('Extracto'),
                                Forms\Components\RichEditor::make('body_pt')
                                    ->fileAttachmentsDisk('public')
                                    ->fileAttachmentsDirectory('blog/attachments')
                                    ->label('Corpo do artigo'),

                                Forms\Components\Section::make('SEO — Português')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->collapsible()
                                    ->schema([
                                        static::slugField(BlogPost::class, 'pt', 'slug_pt', 'Slug (URL) — Português', titleField: 'title_pt')
                                            ->unique(ignoreRecord: true),
                                        Forms\Components\TextInput::make('meta_title_pt')
                                            ->maxLength(70)
                                            ->live()
                                            ->helperText(fn (Forms\Get $get): string => static::seoCharHelper($get('meta_title_pt'), 50, 60))
                                            ->label('Meta title (Português)'),
                                        Forms\Components\Textarea::make('meta_description_pt')
                                            ->maxLength(160)
                                            ->rows(3)
                                            ->live()
                                            ->helperText(fn (Forms\Get $get): string => static::seoCharHelper($get('meta_description_pt'), 150, 160))
                                            ->label('Meta description (Português)'),
                                        Forms\Components\Textarea::make('schema_jsonld_pt')
                                            ->label('Dados estruturados (JSON-LD) — Português')
                                            ->rows(6)
                                            ->helperText('Opcional. Se vazio, usa o do espanhol. Deve ser um JSON válido.')
                                            ->rules([static::seoJsonLdRule()]),
                                    ]),
                            ]),

                        // ── Cover image ───────────────────────────────────
                        Tabs\Tab::make('Imagen')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Forms\Components\FileUpload::make('cover_image')
                                    ->image()
                                    ->disk('public')
                                    ->directory('blog/covers')
                                    ->imageEditor()
                                    ->nullable()
                                    ->saveUploadedFileUsing(\App\Support\ImageOptimizer::saver('blog/covers', 1600, deletePrevious: true))
                                    ->helperText('Se optimiza automáticamente a WebP (máx. 1600px de ancho).')
                                    ->label('Imagen de portada'),
                            ]),

                        // ── Post data ─────────────────────────────────────
                        // El slug (ES/EN/PT) se movió a "SEO — [idioma]" dentro de cada
                        // pestaña de idioma (Español/English/Português).
                        Tabs\Tab::make('Datos')
                            ->icon('heroicon-o-tag')
                            ->schema([
                                Forms\Components\TextInput::make('category')
                                    ->maxLength(255)
                                    ->label('Categoría'),
                                Forms\Components\TagsInput::make('tags')
                                    ->placeholder('Agregar etiqueta')
                                    ->label('Etiquetas'),
                                Forms\Components\TextInput::make('author_name')
                                    ->maxLength(255)
                                    ->label('Nombre del autor'),
                            ]),

                        // ── Publication settings ──────────────────────────
                        Tabs\Tab::make('Publicación')
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                Forms\Components\Toggle::make('is_published')
                                    ->label('Publicado')
                                    ->default(false),
                                Forms\Components\DateTimePicker::make('published_at')
                                    ->nullable()
                                    ->helperText('Deja vacío para publicar inmediatamente al activar el toggle.')
                                    ->label('Fecha de publicación'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title_es')
                    ->searchable()
                    ->limit(50)
                    ->label('Título (ES)'),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->label('Categoría'),
                Tables\Columns\ToggleColumn::make('is_published')
                    ->label('Publicado'),
                Tables\Columns\TextColumn::make('published_at')
                    ->date('d/m/Y')
                    ->sortable()
                    ->label('Fecha'),
                Tables\Columns\TextColumn::make('reading_minutes')
                    ->suffix(' min')
                    ->label('Lectura'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')->label('Publicado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBlogPosts::route('/'),
            'create' => Pages\CreateBlogPost::route('/create'),
            'edit'   => Pages\EditBlogPost::route('/{record}/edit'),
        ];
    }
}
