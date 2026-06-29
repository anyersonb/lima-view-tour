<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TestimonialResource\Pages;
use App\Filament\Resources\TestimonialResource\RelationManagers;
use App\Models\Testimonial;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Contenido';
    protected static ?string $navigationLabel = 'Testimonios';
    protected static ?string $modelLabel = 'Testimonio';
    protected static ?string $pluralModelLabel = 'Testimonios';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('country')
                    ->maxLength(255),
                Forms\Components\TextInput::make('avatar')
                    ->maxLength(255),
                Forms\Components\Textarea::make('quote_es')
                    ->required()
                    ->label('Comentário (Español)')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('quote_en')
                    ->label('Comentário (English)')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('quote_pt')
                    ->label('Comentário (Português)')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('rating')
                    ->required()
                    ->numeric()
                    ->default(5.0),
                Forms\Components\TextInput::make('source')
                    ->required()
                    ->maxLength(255)
                    ->default('Google'),
                Forms\Components\Toggle::make('is_featured')
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
                Forms\Components\TextInput::make('order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Select::make('tour_id')
                    ->label('Tour')
                    ->relationship('tour', 'title_es')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Autor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('quote_es')
                    ->label('Comentario')
                    ->limit(60)
                    ->wrap()
                    ->tooltip(fn ($state) => $state)
                    ->searchable(),
                Tables\Columns\TextColumn::make('tour.title_es')
                    ->label('Tour')
                    ->limit(28)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label('★')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Destacado')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aprobado'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Enviado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Aprobados')
                    ->falseLabel('Pendientes'),
                Tables\Filters\SelectFilter::make('source')
                    ->label('Origen')
                    ->options([
                        'Web' => 'Web (enviadas por usuarios)',
                        'Google' => 'Google',
                        'Tripadvisor' => 'Tripadvisor',
                    ]),
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
            'index' => Pages\ListTestimonials::route('/'),
            'create' => Pages\CreateTestimonial::route('/create'),
            'edit' => Pages\EditTestimonial::route('/{record}/edit'),
        ];
    }
}
