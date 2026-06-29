<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MediaAssetResource\Pages;
use App\Models\MediaAsset;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MediaAssetResource extends Resource
{
    protected static ?string $model = MediaAsset::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationGroup = 'Contenido';
    protected static ?string $navigationLabel = 'Multimedia';
    protected static ?string $modelLabel = 'Archivo';
    protected static ?string $pluralModelLabel = 'Biblioteca de medios';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Subir archivos')
                    ->description('Imágenes, PDFs o vídeos MP4. Máximo 10 MB por archivo.')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->schema([
                        Forms\Components\FileUpload::make('files')
                            ->label('Archivos')
                            ->disk('media')
                            ->directory('biblioteca')
                            ->multiple()
                            // Seguridad: tipos explícitos (sin comodín image/* ni SVG, que es vector XSS)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf', 'video/mp4'])
                            ->maxSize(10240)
                            // Nombres aleatorios (no preserveFilenames): evita sobrescritura y rutas predecibles
                            ->downloadable()
                            ->columnSpanFull()
                            ->visibleOn('create'),

                        Forms\Components\TextInput::make('name')
                            ->label('Nombre legible')
                            ->maxLength(255)
                            ->visibleOn('edit'),

                        Forms\Components\TextInput::make('collection')
                            ->label('Colección / Categoría')
                            ->placeholder('tours, blog, banners…')
                            ->maxLength(100),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail')
                    ->label('')
                    ->getStateUsing(fn (MediaAsset $record): ?string => $record->is_image ? $record->url : null)
                    ->defaultImageUrl(asset('vendor/filament/filament/images/default-avatar.png'))
                    ->square()
                    ->size(56),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('collection')
                    ->label('Colección')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('mime')
                    ->label('Tipo')
                    ->badge()
                    ->color('gray')
                    ->limit(20),

                Tables\Columns\TextColumn::make('size')
                    ->label('Tamaño')
                    ->formatStateUsing(function (?int $state): string {
                        if ($state === null) {
                            return '—';
                        }
                        if ($state >= 1_048_576) {
                            return number_format($state / 1_048_576, 2).' MB';
                        }

                        return number_format($state / 1024, 1).' KB';
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('url')
                    ->label('URL pública')
                    ->getStateUsing(fn (MediaAsset $record): string => $record->url)
                    ->copyable()
                    ->copyMessage('URL copiada')
                    ->limit(45)
                    ->tooltip(fn (MediaAsset $record): string => $record->url),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Subido')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('collection')
                    ->label('Colección')
                    ->options(fn (): array => MediaAsset::query()
                        ->whereNotNull('collection')
                        ->distinct()
                        ->orderBy('collection')
                        ->pluck('collection', 'collection')
                        ->toArray()
                    ),
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
            'index' => Pages\ListMediaAssets::route('/'),
            'create' => Pages\CreateMediaAsset::route('/create'),
            'edit' => Pages\EditMediaAsset::route('/{record}/edit'),
        ];
    }
}
