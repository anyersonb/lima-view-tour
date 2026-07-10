<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Filament\Resources\BookingResource\RelationManagers;
use App\Models\Booking;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Marketing';
    protected static ?string $navigationLabel = 'Reservas';
    protected static ?string $modelLabel = 'Reserva';
    protected static ?string $pluralModelLabel = 'Reservas';
    protected static ?int $navigationSort = 8;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ── Tour ────────────────────────────────────────────────────
                Forms\Components\Section::make('Tour')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Toggle::make('use_custom_tour')
                            ->label('Tour personalizado / "ninguno de los anteriores"')
                            ->helperText('Actívalo si el cliente necesita un tour especializado que no está en el catálogo. Podrás escribir el nombre, la descripción y el precio manualmente.')
                            ->default(false)
                            ->dehydrated(false)
                            ->live()
                            ->columnSpanFull(),

                        // Tour del catálogo (por NOMBRE, no por id)
                        Forms\Components\Select::make('tour_id')
                            ->label('Tour del catálogo')
                            ->relationship('tour', 'title_es')
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Tour $record) => $record->title_es
                                . ' — US$' . number_format((float) $record->price, 2)
                                . ' · cap. ' . ($record->max_capacity ?: '∞'))
                            ->searchable()
                            ->preload()
                            ->required(fn (Forms\Get $get) => ! $get('use_custom_tour'))
                            ->visible(fn (Forms\Get $get) => ! $get('use_custom_tour'))
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                if (! $state) {
                                    return;
                                }
                                $tour = \App\Models\Tour::find($state);
                                if ($tour) {
                                    $set('tour_title_snapshot', $tour->title_es);
                                    $set('unit_price', number_format((float) $tour->price, 2, '.', ''));
                                    self::recalcTotals($set, $get);
                                }
                            })
                            ->helperText('El nombre y el precio se completan solos al elegir el tour.')
                            ->columnSpanFull(),

                        // Nombre del tour (snapshot). Auto para catálogo; editable si es personalizado.
                        Forms\Components\TextInput::make('tour_title_snapshot')
                            ->label(fn (Forms\Get $get) => $get('use_custom_tour') ? 'Nombre del tour personalizado' : 'Nombre del tour (automático)')
                            ->required()
                            ->maxLength(255)
                            ->readOnly(fn (Forms\Get $get) => ! $get('use_custom_tour'))
                            ->columnSpanFull(),

                        // Descripción del tour personalizado
                        Forms\Components\Textarea::make('custom_tour_details')
                            ->label('Descripción del tour personalizado')
                            ->placeholder('Describe qué tipo de tour necesita el cliente: destinos, duración, servicios, requerimientos especiales, etc.')
                            ->rows(4)
                            ->visible(fn (Forms\Get $get) => (bool) $get('use_custom_tour'))
                            ->required(fn (Forms\Get $get) => (bool) $get('use_custom_tour'))
                            ->columnSpanFull(),
                    ]),

                // ── Cliente ─────────────────────────────────────────────────
                Forms\Components\Section::make('Cliente')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('customer_name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('customer_email')
                            ->label('Correo')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('customer_phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('travel_date')
                            ->label('Fecha del tour')
                            ->required()
                            ->native(false),
                    ]),

                // ── Pasajeros y precio ──────────────────────────────────────
                Forms\Components\Section::make('Pasajeros y precio')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('adults')
                            ->label('Adultos')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::recalcTotals($set, $get)),
                        Forms\Components\TextInput::make('children')
                            ->label('Niños')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::recalcTotals($set, $get)),

                        Forms\Components\TextInput::make('unit_price')
                            ->label('Precio por persona')
                            ->prefix('US$')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->readOnly(fn (Forms\Get $get) => ! $get('use_custom_tour'))
                            ->helperText(fn (Forms\Get $get) => $get('use_custom_tour')
                                ? 'Ingresa el precio por persona del tour personalizado.'
                                : 'Se completa automáticamente según el tour del catálogo.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::recalcTotals($set, $get)),

                        Forms\Components\Placeholder::make('pax_hint')
                            ->label('Total de pasajeros')
                            ->content(fn (Forms\Get $get) => ((int) $get('adults') + (int) $get('children')) . ' persona(s)'),

                        // Oferta / descuento especial (manual)
                        Forms\Components\Select::make('discount_type')
                            ->label('Oferta / descuento especial')
                            ->options([
                                'percent' => 'Porcentaje (%)',
                                'fixed'   => 'Monto fijo (US$)',
                            ])
                            ->placeholder('Sin descuento')
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::recalcTotals($set, $get)),
                        Forms\Components\TextInput::make('discount_value')
                            ->label(fn (Forms\Get $get) => $get('discount_type') === 'percent' ? 'Porcentaje a descontar (%)' : 'Monto a descontar (US$)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix(fn (Forms\Get $get) => $get('discount_type') === 'fixed' ? 'US$' : null)
                            ->suffix(fn (Forms\Get $get) => $get('discount_type') === 'percent' ? '%' : null)
                            ->visible(fn (Forms\Get $get) => filled($get('discount_type')))
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::recalcTotals($set, $get)),

                        Forms\Components\Placeholder::make('discount_amount_hint')
                            ->label('Descuento aplicado')
                            ->visible(fn (Forms\Get $get) => filled($get('discount_type')))
                            ->content(fn (Forms\Get $get) => 'US$' . number_format((float) $get('discount_amount'), 2)),

                        Forms\Components\TextInput::make('total_price')
                            ->label('TOTAL a cobrar')
                            ->prefix('US$')
                            ->required()
                            ->numeric()
                            ->readOnly()
                            ->helperText('Se calcula solo: (precio × pasajeros) − descuento.')
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('discount_amount')->default(0),
                        Forms\Components\TextInput::make('currency')
                            ->label('Moneda')
                            ->required()
                            ->maxLength(3)
                            ->default('USD'),
                    ]),

                // ── Estado y pago ───────────────────────────────────────────
                Forms\Components\Section::make('Estado y pago')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Estado de la reserva')
                            ->options([
                                'pending'   => 'Pendiente',
                                'confirmed' => 'Confirmada',
                                'cancelled' => 'Cancelada',
                                'completed' => 'Completada',
                            ])
                            ->default('pending')
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('payment_status')
                            ->label('Estado del pago')
                            ->options([
                                'pending'  => 'Por pagar',
                                'paid'     => 'Pagado',
                                'refunded' => 'Reembolsado',
                            ])
                            ->default('pending')
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // Al marcar pagado, confirma la reserva automáticamente.
                                if ($state === 'paid') {
                                    $set('status', 'confirmed');
                                }
                            }),
                        Forms\Components\Select::make('payment_method')
                            ->label('Método de pago')
                            ->options([
                                'pay_later'    => 'Pagar luego',
                                'paypal'       => 'PayPal',
                                'card'         => 'Pago con tarjeta',
                                'payment_link' => 'Link de pago',
                                'cash'         => 'Efectivo',
                                'transfer'     => 'Transferencia',
                            ])
                            ->default('pay_later')
                            ->native(false)
                            ->live(),
                        Forms\Components\TextInput::make('payment_link_url')
                            ->label('Link de pago (opcional)')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://...')
                            ->helperText('Pega aquí el enlace de pago para enviárselo al cliente. Opcional.')
                            ->visible(fn (Forms\Get $get) => $get('payment_method') === 'payment_link')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Referencia de pago (automática)')
                            ->helperText('Se genera automáticamente (ej. ID de captura de PayPal). No se edita a mano.')
                            ->disabled()
                            ->dehydrated(false)
                            ->visibleOn('edit'),
                    ]),

                // ── Recojo y notas ──────────────────────────────────────────
                Forms\Components\Section::make('Recojo y notas')
                    ->columns(2)
                    ->schema([
                        ...(\Illuminate\Support\Facades\Schema::hasColumn('bookings', 'pickup_point') ? [
                            Forms\Components\TextInput::make('pickup_point')
                                ->label('Punto de recogida (zona)')
                                ->maxLength(100),
                            Forms\Components\TextInput::make('pickup_detail')
                                ->label('Detalle de recogida (hotel/dirección)')
                                ->maxLength(255),
                        ] : []),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas internas')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('locale')
                            ->label('Idioma del cliente')
                            ->options(['es' => 'Español', 'en' => 'English', 'pt' => 'Português'])
                            ->default('es')
                            ->required()
                            ->native(false),
                        Forms\Components\Toggle::make('send_emails')
                            ->label('Enviar correos de confirmación (cliente + administrador)')
                            ->helperText('Envía el correo de confirmación al cliente y la notificación interna. Desactívalo para cargar reservas históricas sin notificar.')
                            ->default(true)
                            ->dehydrated(false)
                            ->visibleOn('create')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Recalcula el descuento y el total en vivo:
     *   bruto = precio_por_persona × (adultos + niños)
     *   descuento = % del bruto  ó  monto fijo (tope: el bruto)
     *   total = bruto − descuento
     */
    public static function recalcTotals(Forms\Set $set, Forms\Get $get): void
    {
        $unit = (float) $get('unit_price');
        $pax  = max(0, (int) $get('adults') + (int) $get('children'));
        $gross = round($unit * $pax, 2);

        $type = $get('discount_type');
        $val  = (float) $get('discount_value');
        $discount = 0.0;

        if ($type === 'percent') {
            $discount = round($gross * min(max($val, 0), 100) / 100, 2);
        } elseif ($type === 'fixed') {
            $discount = min(round(max($val, 0), 2), $gross);
        }

        $set('discount_amount', number_format($discount, 2, '.', ''));
        $set('total_price', number_format(max(0, $gross - $discount), 2, '.', ''));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Referencia')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tour_title_snapshot')
                    ->label('Tour')
                    ->description(fn ($record) => $record->tour_id ? null : 'Personalizado')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer_email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer_phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('travel_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('adults')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('children')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('P. unitario')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Descuento')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending'   => 'Pendiente',
                        'confirmed' => 'Confirmada',
                        'cancelled' => 'Cancelada',
                        'completed' => 'Completada',
                        default     => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'confirmed', 'completed' => 'success',
                        'cancelled'              => 'danger',
                        default                  => 'warning',
                    }),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Pago')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'paid'     => 'Pagado',
                        'pending'  => 'Por pagar',
                        'refunded' => 'Reembolsado',
                        default    => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'paid'     => 'success',
                        'refunded' => 'gray',
                        default    => 'warning',
                    }),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Método')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'pay_later'    => 'Pagar luego',
                        'paypal'       => 'PayPal',
                        'card'         => 'Pago con tarjeta',
                        'payment_link' => 'Link de pago',
                        'cash'         => 'Efectivo',
                        'transfer'     => 'Transferencia',
                        default        => $state ?: '—',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('payment_reference')
                    ->label('Ref. pago')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('payment_link_url')
                    ->label('Link de pago')
                    ->url(fn ($record) => $record->payment_link_url, shouldOpenInNewTab: true)
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                ...(\Illuminate\Support\Facades\Schema::hasColumn('bookings', 'pickup_point') ? [
                    Tables\Columns\TextColumn::make('pickup_point')
                        ->label('Recogida')
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ] : []),
                Tables\Columns\TextColumn::make('locale')
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
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
}
