<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TestimonialResource\Pages;
use App\Filament\Resources\TestimonialResource\RelationManagers;
use App\Models\Testimonial;
use App\Support\ImageOptimizer;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
                Forms\Components\TextInput::make('title_es')
                    ->label('Título (Español)')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('title_en')
                    ->label('Título (English)')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('title_pt')
                    ->label('Título (Português)')
                    ->maxLength(255)
                    ->columnSpanFull(),
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
                    ->minValue(1)
                    ->maxValue(5)
                    ->default(5.0),
                Forms\Components\Select::make('traveler_type')
                    ->label('Tipo de viaje')
                    ->options([
                        'solo'      => 'Solo',
                        'pareja'    => 'En pareja',
                        'familia'   => 'En familia',
                        'amigos'    => 'Con amigos',
                        'negocios'  => 'De negocios',
                    ])
                    ->native(false)
                    ->helperText('Vacío = la ficha no muestra esa línea.'),
                Forms\Components\DatePicker::make('review_date')
                    ->label('Fecha de la reseña')
                    ->native(false)
                    ->helperText('Vacío = se usa la fecha de creación del registro.'),
                Forms\Components\TextInput::make('source')
                    ->required()
                    ->maxLength(255)
                    ->default('Google'),
                Forms\Components\Select::make('status')
                    ->label('Estado de moderación')
                    ->options([
                        'pending'  => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    ])
                    ->default('pending')
                    ->native(false)
                    ->required()
                    ->helperText('Solo "Aprobado" + "Aprobado" (interruptor de abajo) sale al sitio público.'),
                Forms\Components\Toggle::make('is_featured')
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Aprobado (visible en el sitio)')
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
                Forms\Components\FileUpload::make('photos')
                    ->label('Fotos del viajero')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->disk('public')
                    ->directory('reviews')
                    ->maxSize(4096)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->rules([static::photoExtensionRule()])
                    // Hallazgo de seguridad #5 (2026-09-30): el nombre en disco
                    // NUNCA sale de getClientOriginalExtension() (lo que el
                    // navegador dice), siempre del MIME detectado del propio
                    // archivo, restringido a jpg/png/webp — ver
                    // ImageOptimizer::safeImageNamer(). Cierra el vector de
                    // un JPEG políglota (imagen real + PHP anexado) subido
                    // como "algo.php": antes se guardaba tal cual con esa
                    // extensión; ahora siempre termina en .jpg/.png/.webp o,
                    // si el MIME detectado no es ninguno de esos tres, en
                    // .bin (nunca ejecutable).
                    ->getUploadedFileNameForStorageUsing(ImageOptimizer::safeImageNamer([
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                    ]))
                    ->helperText('JPG, PNG o WEBP, máx. 4MB cada una. No se muestran hoy en el sitio (fase 2).')
                    ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                        default => $state,
                    })
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Visible'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Enviado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                // Sin default: la cola de pendientes debe verse desde el primer
                // vistazo, sin que el admin tenga que tocar ningún filtro.
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending'  => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Visible')
                    ->placeholder('Todos')
                    ->trueLabel('Visibles')
                    ->falseLabel('Ocultos'),
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
                    Tables\Actions\BulkAction::make('approve')
                        ->label('Aprobar')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(
                            fn (Testimonial $t) => $t->update(['status' => 'approved'])
                        ))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('reject')
                        ->label('Rechazar')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(
                            fn (Testimonial $t) => $t->update(['status' => 'rejected'])
                        ))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * `->image()`/`->acceptedFileTypes()` de Filament solo validan el MIME
     * type reportado por el navegador, no la extensión real del archivo del
     * cliente (ver App\Filament\Concerns\HasLocalizedSeoFields::seoJsonLdRule
     * para el mismo problema con otro campo). Regla explícita adicional
     * contra extensiones peligrosas disfrazadas de imagen.
     *
     * Debe devolver un closure de CERO parámetros: Filament resuelve
     * ->rules() con inyección de dependencias por reflexión y el closure de
     * validación de Laravel (string $attribute, $value, Closure $fail) no es
     * inyectable — devolverlo directo revienta con BindingResolutionException
     * en cualquier guardado, sin importar el contenido del campo.
     *
     * Hallazgo de seguridad #5 (2026-09-30): Filament aplica esta regla POR
     * ARCHIVO (BaseFileUpload::getValidationRules(), "{$name}.*"), así que
     * $value YA es un único TemporaryUploadedFile, nunca un array de
     * archivos. El `(array) $value` de antes casteaba ese OBJETO a un array
     * de sus propiedades internas (no "un array con el objeto adentro"): el
     * foreach nunca encontraba nada con getClientOriginalExtension() y la
     * regla pasaba SIEMPRE, con cualquier extensión — código muerto
     * verificado con un PoC directo (shell.php pasaba). Se valida $value
     * directamente.
     */
    protected static function photoExtensionRule(): Closure
    {
        return fn (): Closure => function (string $attribute, $value, Closure $fail): void {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (! is_object($value) || ! method_exists($value, 'getClientOriginalExtension')) {
                return;
            }

            $extension = strtolower($value->getClientOriginalExtension());

            if (! in_array($extension, $allowed, true)) {
                $fail("Extensión de archivo no permitida ({$extension}). Solo se aceptan: ".implode(', ', $allowed).'.');
            }
        };
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
