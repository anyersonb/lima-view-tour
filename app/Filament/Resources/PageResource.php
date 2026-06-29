<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Filament\Resources\PageResource\RelationManagers;
use App\Models\Page;
use App\Support\ImagePath;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255),
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
                Forms\Components\Textarea::make('content_es')
                    ->label('Contenido (Español)')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('content_en')
                    ->label('Content (English)')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('content_pt')
                    ->label('Conteúdo (Português)')
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('hero_image')
                    ->image(),
                Forms\Components\TextInput::make('blocks'),
                Forms\Components\TextInput::make('seo_title')
                    ->maxLength(255),
                Forms\Components\TextInput::make('seo_description')
                    ->maxLength(320),
                Forms\Components\FileUpload::make('seo_image')
                    ->image(),
                Forms\Components\Toggle::make('is_published')
                    ->required(),
                Forms\Components\Toggle::make('show_in_sitemap')
                    ->required(),
                Forms\Components\TextInput::make('sitemap_priority')
                    ->required()
                    ->maxLength(255)
                    ->default(0.5),
                Forms\Components\TextInput::make('sitemap_changefreq')
                    ->required()
                    ->maxLength(255)
                    ->default('monthly'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title_es')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title_en')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('hero_image')
                    ->getStateUsing(fn ($record) => ImagePath::url($record->hero_image)),
                Tables\Columns\TextColumn::make('seo_title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('seo_description')
                    ->searchable(),
                Tables\Columns\ImageColumn::make('seo_image')
                    ->getStateUsing(fn ($record) => ImagePath::url($record->seo_image)),
                Tables\Columns\IconColumn::make('is_published')
                    ->boolean(),
                Tables\Columns\IconColumn::make('show_in_sitemap')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sitemap_priority')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sitemap_changefreq')
                    ->searchable(),
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
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
