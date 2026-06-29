<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlockedDateResource\Pages;
use App\Models\BlockedDate;
use App\Models\Tour;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BlockedDateResource extends Resource
{
    protected static ?string $model = BlockedDate::class;

    protected static ?string $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'Reservas';
    protected static ?string $navigationLabel = 'Fechas bloqueadas';
    protected static ?string $modelLabel      = 'Fecha bloqueada';
    protected static ?string $pluralModelLabel = 'Fechas bloqueadas';
    protected static ?int    $navigationSort  = 20;

    // ─────────────────────────────────────────────────────────────────────────
    // FORM
    // ─────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Tipo de bloqueo')
                    ->schema([
                        Forms\Components\Radio::make('type')
                            ->label('Tipo de bloqueo')
                            ->options([
                                'date'    => 'Fecha específica',
                                'weekday' => 'Día de la semana (recurrente)',
                            ])
                            ->default('date')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                // Clear the field that is no longer relevant
                                if ($state === 'date') {
                                    $set('weekday', null);
                                } else {
                                    $set('date', null);
                                }
                            }),

                        Forms\Components\DatePicker::make('date')
                            ->label('Fecha a bloquear')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->minDate(now()->toDateString())
                            ->required(fn (Get $get) => $get('type') === 'date')
                            ->visible(fn (Get $get) => $get('type') === 'date')
                            ->dehydrated(fn (Get $get) => $get('type') === 'date'),

                        Forms\Components\Select::make('weekday')
                            ->label('Día de la semana')
                            ->options([
                                0 => 'Domingo',
                                1 => 'Lunes',
                                2 => 'Martes',
                                3 => 'Miércoles',
                                4 => 'Jueves',
                                5 => 'Viernes',
                                6 => 'Sábado',
                            ])
                            ->required(fn (Get $get) => $get('type') === 'weekday')
                            ->visible(fn (Get $get) => $get('type') === 'weekday')
                            ->dehydrated(fn (Get $get) => $get('type') === 'weekday'),
                    ]),

                Forms\Components\Section::make('Alcance')
                    ->schema([
                        Forms\Components\Select::make('tour_id')
                            ->label('Aplica a')
                            ->options(function () {
                                $tours = Tour::published()
                                    ->orderBy('title_es')
                                    ->pluck('title_es', 'id');
                                return $tours;
                            })
                            ->placeholder('Todos los tours')
                            ->nullable()
                            ->searchable()
                            ->helperText('Deja en blanco para bloquear en todos los tours.'),

                        Forms\Components\TextInput::make('reason')
                            ->label('Motivo (opcional)')
                            ->maxLength(255)
                            ->placeholder('Ej: Feriado nacional, mantenimiento…'),
                    ]),
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TABLE
    // ─────────────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        $weekdayNames = [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
        ];

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type_display')
                    ->label('Bloqueo')
                    ->state(function (BlockedDate $record) use ($weekdayNames): string {
                        if ($record->date !== null) {
                            return $record->date->format('d/m/Y');
                        }
                        $day = $weekdayNames[$record->weekday] ?? "Día {$record->weekday}";
                        return "Todos los {$day}s";
                    })
                    ->searchable(false)
                    ->sortable(false),

                Tables\Columns\TextColumn::make('tour.title_es')
                    ->label('Aplica a')
                    ->default('Todos los tours')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Motivo')
                    ->default('—')
                    ->limit(40),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
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

    // ─────────────────────────────────────────────────────────────────────────
    // PAGES
    // ─────────────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBlockedDates::route('/'),
            'create' => Pages\CreateBlockedDate::route('/create'),
            'edit'   => Pages\EditBlockedDate::route('/{record}/edit'),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Mutate form data: ensure the unused field is always null before saving
    // ─────────────────────────────────────────────────────────────────────────

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        return self::normalizeData($data);
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        return self::normalizeData($data);
    }

    private static function normalizeData(array $data): array
    {
        if (($data['type'] ?? null) === 'date') {
            $data['weekday'] = null;
        } else {
            $data['date'] = null;
        }
        // Remove the virtual "type" field — it is not a DB column
        unset($data['type']);

        return $data;
    }
}
