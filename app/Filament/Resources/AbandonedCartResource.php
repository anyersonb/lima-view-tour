<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AbandonedCartResource\Pages;
use App\Mail\AbandonedCartReminder;
use App\Models\AbandonedCart;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class AbandonedCartResource extends Resource
{
    protected static ?string $model = AbandonedCart::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-x-mark';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Carritos abandonados';

    protected static ?string $modelLabel = 'Carrito abandonado';

    protected static ?string $pluralModelLabel = 'Carritos abandonados';

    protected static ?int $navigationSort = 9;

    public static function getNavigationBadge(): ?string
    {
        // Defensa: si la tabla aún no existe (migración pendiente en prod)
        // no romper el panel entero con la consulta del badge.
        if (! Schema::hasTable('abandoned_carts')) {
            return null;
        }

        return static::getModel()::where('status', 'active')
            ->whereNotNull('email')
            ->count() ?: null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Schema::hasTable('abandoned_carts');
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    /**
     * Vista de detalle (página View): todo lo necesario para contactar y
     * entender el carrito en una sola pantalla, sin volcar el JSON crudo.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Contacto')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre')
                            ->placeholder('(sin nombre)'),
                        TextEntry::make('email')
                            ->label('Correo')
                            ->placeholder('(sin correo)')
                            ->copyable()
                            ->copyMessage('Correo copiado')
                            ->url(fn (AbandonedCart $record): ?string => $record->mailto_url)
                            ->color(fn (AbandonedCart $record) => $record->mailto_url ? 'primary' : null),
                        TextEntry::make('phone')
                            ->label('Teléfono')
                            ->placeholder('(sin teléfono)')
                            ->copyable()
                            ->copyMessage('Teléfono copiado')
                            ->url(fn (AbandonedCart $record): ?string => $record->whatsapp_url)
                            ->openUrlInNewTab()
                            ->color(fn (AbandonedCart $record) => $record->whatsapp_url ? 'success' : null)
                            ->helperText(fn (AbandonedCart $record) => $record->whatsapp_url ? 'Clic para abrir WhatsApp' : null),
                        TextEntry::make('locale')
                            ->label('Idioma en que navegaba')
                            ->formatStateUsing(fn (string $state) => match ($state) {
                                'en' => 'Inglés',
                                'pt' => 'Portugués',
                                default => 'Español',
                            }),
                        TextEntry::make('customer_id')
                            ->label('Cliente registrado')
                            ->formatStateUsing(fn (?int $state) => $state ? 'Sí' : 'No (invitado)')
                            ->badge()
                            ->color(fn (?int $state) => $state ? 'success' : 'gray'),
                        TextEntry::make('recovery_url')
                            ->label('Enlace de recuperación')
                            ->placeholder('—')
                            ->copyable()
                            ->copyMessage('Enlace copiado')
                            ->url(fn (AbandonedCart $record): ?string => $record->recovery_url)
                            ->openUrlInNewTab(),
                    ]),

                InfolistSection::make('Cliente registrado')
                    ->description('Datos de su perfil de cliente — puede tener teléfono aunque el carrito no lo haya capturado.')
                    ->columns(3)
                    ->visible(fn (AbandonedCart $record) => $record->customer_id !== null)
                    ->schema([
                        TextEntry::make('customer.name')->label('Nombre (perfil)')->placeholder('—'),
                        TextEntry::make('customer.email')->label('Correo (perfil)')->placeholder('—'),
                        TextEntry::make('customer.phone')
                            ->label('Teléfono (perfil)')
                            ->placeholder('—')
                            ->copyable(),
                    ]),

                InfolistSection::make('Estado del carrito')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (string $state) => match ($state) {
                                'active' => 'Activo',
                                'converted' => 'Convertido',
                                'expired' => 'Caducado',
                                default => $state,
                            })
                            ->color(fn (string $state) => match ($state) {
                                'converted' => 'success',
                                'expired' => 'gray',
                                default => 'warning',
                            }),
                        TextEntry::make('subtotal')->label('Subtotal')->money('USD'),
                        TextEntry::make('total')->label('Total')->money('USD'),
                        TextEntry::make('coupon_code')->label('Cupón usado')->placeholder('—'),
                        TextEntry::make('reminders_sent')->label('Recordatorios enviados'),
                        TextEntry::make('last_activity_at')->label('Última actividad')->dateTime('d/m/Y H:i'),
                        TextEntry::make('last_reminder_at')->label('Último recordatorio')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('converted_at')->label('Convertido el')->dateTime('d/m/Y H:i')->placeholder('—'),
                    ]),

                InfolistSection::make('Tours en el carrito')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('title_snapshot')->label('Tour')->columnSpan(2),
                                TextEntry::make('travel_date')->label('Fecha')
                                    ->formatStateUsing(fn (?string $state) => $state ? Carbon::parse($state)->format('d/m/Y') : '—'),
                                TextEntry::make('adults')->label('Adultos'),
                                TextEntry::make('children')->label('Niños'),
                                TextEntry::make('unit_price')->label('Precio unit.')->money('USD'),
                                TextEntry::make('subtotal')->label('Subtotal')->money('USD'),
                            ])
                            ->columns(7),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('customer:id,name,email,phone'))
            ->defaultSort('last_activity_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->placeholder('(sin nombre)'),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->placeholder('(sin correo)')
                    ->url(fn (AbandonedCart $r) => $r->mailto_url)
                    ->icon('heroicon-o-envelope'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->getStateUsing(fn (AbandonedCart $r) => $r->effective_phone)
                    ->placeholder('(sin teléfono)')
                    ->description(fn (AbandonedCart $r) => (! $r->phone && $r->customer?->phone) ? 'Del perfil del cliente' : null),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Ítems')
                    ->badge(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'active' => 'Activo',
                        'converted' => 'Convertido',
                        'expired' => 'Caducado',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'converted' => 'success',
                        'expired' => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('reminders_sent')
                    ->label('Recordatorios')
                    ->badge(),
                Tables\Columns\TextColumn::make('locale')
                    ->label('Idioma')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'en' => 'Inglés',
                        'pt' => 'Portugués',
                        default => 'Español',
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_activity_at')
                    ->label('Última actividad')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_reminder_at')
                    ->label('Último recordatorio')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active' => 'Activo',
                        'converted' => 'Convertido',
                        'expired' => 'Caducado',
                    ]),
                Tables\Filters\TernaryFilter::make('has_phone')
                    ->label('Teléfono')
                    ->trueLabel('Con teléfono')
                    ->falseLabel('Sin teléfono')
                    ->placeholder('Todos')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('phone'),
                        false: fn (Builder $q) => $q->whereNull('phone'),
                    ),
                Tables\Filters\TernaryFilter::make('has_email')
                    ->label('Correo')
                    ->trueLabel('Con correo')
                    ->falseLabel('Sin correo')
                    ->placeholder('Todos')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('email'),
                        false: fn (Builder $q) => $q->whereNull('email'),
                    ),
                Tables\Filters\TernaryFilter::make('has_reminders')
                    ->label('Recordatorios')
                    ->trueLabel('Ya se le envió alguno')
                    ->falseLabel('Ninguno enviado')
                    ->placeholder('Todos')
                    ->queries(
                        true: fn (Builder $q) => $q->where('reminders_sent', '>', 0),
                        false: fn (Builder $q) => $q->where('reminders_sent', 0),
                    ),
                Tables\Filters\Filter::make('last_activity_range')
                    ->label('Última actividad')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Desde'),
                        Forms\Components\DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('last_activity_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('last_activity_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'Desde '.Carbon::parse($data['from'])->format('d/m/Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Hasta '.Carbon::parse($data['until'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),
                Tables\Filters\Filter::make('total_range')
                    ->label('Monto del carrito')
                    ->form([
                        Forms\Components\TextInput::make('min')->label('Mínimo')->numeric()->prefix('$'),
                        Forms\Components\TextInput::make('max')->label('Máximo')->numeric()->prefix('$'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['min'] ?? null, fn (Builder $q, $min) => $q->where('total', '>=', $min))
                            ->when($data['max'] ?? null, fn (Builder $q, $max) => $q->where('total', '<=', $max));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['min'] ?? null) {
                            $indicators[] = 'Desde $'.$data['min'];
                        }
                        if ($data['max'] ?? null) {
                            $indicators[] = 'Hasta $'.$data['max'];
                        }

                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn (AbandonedCart $r) => $r->whatsapp_url)
                    ->openUrlInNewTab()
                    ->visible(fn (AbandonedCart $r) => filled($r->whatsapp_url)),
                Tables\Actions\Action::make('call')
                    ->label('Llamar')
                    ->icon('heroicon-o-phone')
                    ->url(fn (AbandonedCart $r) => $r->tel_url)
                    ->visible(fn (AbandonedCart $r) => filled($r->tel_url)),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('resend')
                    ->label('Reenviar recordatorio')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AbandonedCart $r) => $r->isRecoverable())
                    ->action(function (AbandonedCart $r) {
                        $next = min($r->reminders_sent + 1, 2);
                        Mail::to($r->email)->send(new AbandonedCartReminder($r, $next));
                        $r->forceFill([
                            'reminders_sent' => $next,
                            'last_reminder_at' => now(),
                        ])->save();

                        \Filament\Notifications\Notification::make()
                            ->title('Recordatorio enviado a '.$r->email)
                            ->success()
                            ->send();
                    }),
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
            'index' => Pages\ListAbandonedCarts::route('/'),
            'view' => Pages\ViewAbandonedCart::route('/{record}'),
        ];
    }
}
