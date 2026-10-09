<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentLinkResource\Pages;
use App\Models\PaymentLink;
use App\Models\Tour;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class PaymentLinkResource extends Resource
{
    protected static ?string $model = PaymentLink::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Links de pago';

    protected static ?string $modelLabel = 'Link de pago';

    protected static ?string $pluralModelLabel = 'Links de pago';

    protected static ?int $navigationSort = 9;

    /** Idiomas que el admin puede fijar para el cliente (ver PaymentLinkController::resolveLocale). */
    public const LOCALE_OPTIONS = [
        'es' => 'Español',
        'en' => 'English',
        'pt' => 'Português',
    ];

    /**
     * Item 5 (docs/payment-links/QA.md, hallazgo ALTO): se calcula en CADA
     * página del panel (sidebar) — sin este guardián, si el código llega a
     * desplegarse antes que la migración de payment_links (o la tabla se
     * pierde por cualquier motivo), la consulta revienta y tumba TODO
     * /admin, no solo este recurso.
     */
    public static function getNavigationBadge(): ?string
    {
        if (! Schema::hasTable('payment_links')) {
            return null;
        }

        try {
            return static::getModel()::where('status', 'pending')->count() ?: null;
        } catch (\Throwable $e) {
            Log::warning('payment_link_resource.navigation_badge_failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    public static function form(Form $form): Form
    {
        // Un link ya pagado/vencido/anulado no se edita: sus datos pasaron a
        // ser el registro histórico de lo que se cobró. El formulario se
        // muestra (para poder revisarlo desde "Editar") pero deshabilitado
        // por completo — Duplicar es el camino para reutilizar sus datos.
        $lockedUnlessPending = fn (?PaymentLink $record) => $record && $record->status !== 'pending';

        return $form
            ->schema([
                Forms\Components\Section::make('Tour y monto')
                    ->columns(2)
                    ->disabled($lockedUnlessPending)
                    ->schema([
                        Forms\Components\Select::make('tour_id')
                            ->label('Tour')
                            ->relationship('tour', 'title_es')
                            ->getOptionLabelFromRecordUsing(fn (Tour $record) => $record->title_es.' — US$'.number_format((float) $record->price, 2))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, Forms\Set $set, Forms\Get $get) => self::recalcAmount($set, $get))
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('adults')
                            ->label('Adultos')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::recalcAmount($set, $get)),
                        Forms\Components\TextInput::make('children')
                            ->label('Niños')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, Forms\Get $get) => self::recalcAmount($set, $get)),

                        Forms\Components\TextInput::make('amount')
                            ->label('Monto a cobrar')
                            ->prefix('US$')
                            ->numeric()
                            ->minValue(0.01)
                            ->required()
                            ->helperText('Se precarga con precio del tour × pasajeros. Editable: puedes ajustarlo para un descuento, un cobro parcial, o un monto personalizado.')
                            ->columnSpanFull(),

                        Forms\Components\DatePicker::make('travel_date')
                            ->label('Fecha del tour (opcional)')
                            ->native(false)
                            ->helperText('Déjalo vacío si la fecha se coordinará después.'),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Vence el (opcional)')
                            ->native(false)
                            ->helperText('Después de esta fecha el link deja de aceptar pagos.'),
                    ]),

                Forms\Components\Section::make('Cliente (opcional)')
                    ->description('Se precarga en el formulario público, pero el comprador puede corregirlo al pagar.')
                    ->columns(2)
                    ->disabled($lockedUnlessPending)
                    ->schema([
                        Forms\Components\TextInput::make('customer_name')
                            ->label('Nombre')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('customer_email')
                            ->label('Correo')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('customer_phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(255),
                    ]),

                Forms\Components\Section::make('Configuración')
                    ->columns(2)
                    ->disabled($lockedUnlessPending)
                    ->schema([
                        // N-1 (docs/payment-links/SECURITY.md, decisión del
                        // coordinador 2026-09-25): se eliminó el toggle —
                        // TODO link es de un solo uso, sin excepción.
                        // PaymentLink::saving() lo fuerza siempre, así que ni
                        // siquiera hace falta un campo oculto acá.
                        Forms\Components\Select::make('locale')
                            ->label('Idioma del cliente')
                            ->options(self::LOCALE_OPTIONS)
                            ->in(array_keys(self::LOCALE_OPTIONS))
                            ->placeholder('Automático por navegador')
                            ->native(false)
                            ->helperText('Idioma de la página de pago, PayPal, errores, página de gracias y correo. Vacío = según el navegador del cliente.')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('note')
                            ->label('Nota interna')
                            ->helperText('Solo visible en el panel — no se muestra al comprador.')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Estado')
                    ->columns(2)
                    ->visibleOn('edit')
                    ->schema([
                        Forms\Components\Placeholder::make('code_display')
                            ->label('Código')
                            ->content(fn (?PaymentLink $record) => $record?->code),
                        Forms\Components\Placeholder::make('status_display')
                            ->label('Estado')
                            ->content(fn (?PaymentLink $record) => match ($record?->status) {
                                'pending' => 'Pendiente',
                                'paid' => 'Pagado',
                                'expired' => 'Vencido',
                                'cancelled' => 'Anulado',
                                'refunded' => 'Reembolsado',
                                'denied' => 'Rechazado',
                                default => $record?->status,
                            }),
                        // Item 6 (docs/payment-links/QA.md, hallazgo ALTO):
                        // el único respaldo manual de "conseguir la URL
                        // completa" cuando el copiado con un clic falla (o
                        // en móvil). Placeholder con HTML crudo — NO un
                        // TextInput — porque 'url' es un accessor
                        // calculado (PaymentLink::getUrlAttribute()), no
                        // una columna: un TextInput lo mandaría de vuelta
                        // en el guardado y Eloquent reventaría con "Unknown
                        // column".
                        Forms\Components\Placeholder::make('client_url')
                            ->label('Enlace para el cliente')
                            ->columnSpanFull()
                            ->content(fn (?PaymentLink $record) => $record ? new HtmlString(
                                '<div class="flex items-center gap-2">'
                                .'<input type="text" readonly value="'.e($record->url).'" '
                                .'onclick="this.select()" aria-label="Enlace para el cliente" '
                                .'class="fi-input block w-full flex-1 rounded-lg border-gray-300 py-1.5 text-sm '
                                .'dark:border-gray-600 dark:bg-gray-700" style="min-width:0;">'
                                .'<button type="button" data-copy-text="'.e($record->url).'" '
                                .'class="fi-btn fi-btn-size-md fi-color-gray fi-btn-color-gray inline-flex shrink-0 '
                                .'items-center justify-center gap-1 rounded-lg border border-gray-300 px-3 py-2 '
                                .'text-sm font-medium hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-600">'
                                .'Copiar</button>'
                                .'</div>'
                            ) : null),
                    ]),
            ]);
    }

    /**
     * UX (re-auditoría post-FIX-1): título exacto de la notificación del
     * borrado en lote — cuántos se borraron y cuántos se protegieron por
     * tener un cobro registrado (paypal_capture_id no nulo), en vez del
     * "Eliminado" genérico que Filament muestra por defecto sin importar si
     * el lote quedó incompleto.
     */
    public static function bulkDeleteNotificationTitle(int $deletedCount, int $protectedCount): string
    {
        if ($protectedCount === 0) {
            return $deletedCount === 1 ? '1 link eliminado' : "{$deletedCount} links eliminados";
        }

        $deletedLabel = $deletedCount === 1 ? '1 eliminado' : "{$deletedCount} eliminados";
        $protectedLabel = $protectedCount === 1
            ? '1 protegido (tiene un cobro registrado)'
            : "{$protectedCount} protegidos (tienen un cobro registrado)";

        return "{$deletedLabel}, {$protectedLabel}";
    }

    /**
     * Precarga el monto = precio del tour × (adultos + niños). Solo cuando
     * el admin todavía no lo tocó a mano (create) — en edición, si ya hay un
     * tour Y un monto, tocar adultos/niños después de guardado no debería
     * recalcular por sorpresa un monto que el admin ya ajustó. Simplicidad:
     * se recalcula siempre en vivo mientras se completa el formulario; si el
     * admin quiere un monto distinto al automático, lo edita DESPUÉS de fijar
     * tour/pax (el campo queda editable, no readonly).
     */
    public static function recalcAmount(Forms\Set $set, Forms\Get $get): void
    {
        $tourId = $get('tour_id');
        if (! $tourId) {
            return;
        }

        $tour = Tour::find($tourId);
        if (! $tour) {
            return;
        }

        $pax = max(1, (int) $get('adults') + (int) $get('children'));
        $set('amount', number_format((float) $tour->price * $pax, 2, '.', ''));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Item 6 (docs/payment-links/QA.md, hallazgo ALTO): ->copyable()
                // depende de navigator.clipboard, que no existe fuera de un
                // contexto seguro (https/localhost) — en local
                // (http://lima-tour.test) rompía en silencio (Alpine
                // Expression Error) sin ningún respaldo. data-copy-text +
                // el listener global de
                // resources/views/filament/partials/copy-fallback-script.blade.php
                // funcionan en http Y https.
                Tables\Columns\TextColumn::make('url')
                    ->label('Enlace')
                    ->limit(45)
                    ->tooltip(fn (PaymentLink $record) => $record->url)
                    // FIX-3 (docs/payment-links/FIX-2.md): esta celda NO debe
                    // heredar el link de fila por defecto de Filament (que
                    // lleva a Editar) — clic aquí debe SOLO copiar el
                    // enlace, nunca navegar. ->disabledClick() suprime el
                    // <a> que column.blade.php envuelve alrededor de la
                    // celda cuando la columna no define su propia url/action
                    // (ver Filament\Tables\Columns\Concerns\CanBeDisabled).
                    ->disabledClick()
                    ->extraAttributes(fn (PaymentLink $record) => [
                        'data-copy-text' => $record->url,
                        'data-copy-message' => 'Enlace copiado',
                        'class' => 'cursor-pointer',
                        'title' => 'Clic para copiar',
                    ]),
                Tables\Columns\TextColumn::make('tour.title_es')
                    ->label('Tour')
                    ->description(fn (PaymentLink $record) => $record->tour_id ? null : '—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('locale')
                    ->label('Idioma')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => self::LOCALE_OPTIONS[$state] ?? 'Automático')
                    ->color(fn (?string $state) => $state ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Monto')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'Pendiente',
                        'paid' => 'Pagado',
                        'expired' => 'Vencido',
                        'cancelled' => 'Anulado',
                        'refunded' => 'Reembolsado',
                        'denied' => 'Rechazado',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'cancelled', 'denied' => 'danger',
                        'expired', 'refunded' => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('booking.reference')
                    ->label('Reserva')
                    ->placeholder('—')
                    ->url(fn (PaymentLink $record) => $record->booking_id
                        ? \App\Filament\Resources\BookingResource::getUrl('edit', ['record' => $record->booking_id])
                        : null),
                Tables\Columns\TextColumn::make('customer_email')
                    ->label('Cliente (prefill)')
                    ->placeholder('—')
                    ->searchable(),
                // A-1: quién PAGÓ realmente (nunca el prefill del admin) —
                // ver docs/payment-links/SECURITY.md.
                Tables\Columns\TextColumn::make('buyer_email')
                    ->label('Pagó (real)')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Vence')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'paid' => 'Pagado',
                        'expired' => 'Vencido',
                        'cancelled' => 'Anulado',
                        'refunded' => 'Reembolsado',
                        'denied' => 'Rechazado',
                    ])
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\Action::make('cancel')
                    ->label('Anular')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PaymentLink $record) => $record->status === 'pending')
                    ->action(function (PaymentLink $record): void {
                        $record->update(['status' => 'cancelled']);

                        Notification::make()
                            ->success()
                            ->title('Link anulado')
                            ->send();
                    }),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicar')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (PaymentLink $record) {
                        // A-1 (docs/payment-links/SECURITY.md): además de
                        // los campos de estado/PayPal, se excluye TODO dato
                        // de contacto (customer_* Y buyer_*) — un link
                        // duplicado es una plantilla nueva para compartir,
                        // nunca debe arrastrar la PII de a quién iba
                        // dirigido el original ni de quién pagó.
                        $copy = $record->replicate([
                            'code', 'status', 'paid_at', 'paypal_order_id',
                            'paypal_capture_id', 'booking_id', 'created_at', 'updated_at',
                            'customer_name', 'customer_email', 'customer_phone',
                            'buyer_name', 'buyer_email', 'buyer_phone',
                        ]);
                        $copy->code = PaymentLink::generateUniqueCode();
                        $copy->status = 'pending';
                        $copy->save();

                        Notification::make()
                            ->success()
                            ->title('Link duplicado')
                            ->body('Se creó un nuevo link pendiente. Los datos de contacto NO se copiaron: complétalos si corresponde.')
                            ->send();

                        return redirect(static::getUrl('edit', ['record' => $copy]));
                    }),
                // Item 6: acción de fila dedicada — la celda "Enlace" ya es
                // copiable con un clic, pero en móvil un botón explícito es
                // más confiable que acertarle al texto truncado.
                Tables\Actions\Action::make('copy_link')
                    ->label('Copiar enlace')
                    ->icon('heroicon-o-clipboard')
                    ->action(fn () => null)
                    ->extraAttributes(fn (PaymentLink $record) => [
                        'data-copy-text' => $record->url,
                        'data-copy-message' => 'Enlace copiado',
                    ]),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // B-5 (docs/payment-links/SECURITY.md): el guardián real
                    // vive en PaymentLink::booted() ('deleting' devuelve
                    // false para un link con un cobro registrado). El
                    // closure POR DEFECTO de DeleteBulkAction usa
                    // Collection::each(), que ABORTA el resto del lote en
                    // cuanto UN callback devuelve false
                    // (Illuminate\Support\Collection::each() trata `false`
                    // como "romper el foreach") — así que un solo link
                    // protegido en la selección, si caía ANTES que los
                    // demás, dejaba SIN BORRAR también a los que sí
                    // correspondía borrar. ->using() con un foreach normal
                    // evita ese efecto secundario y procesa el lote completo.
                    //
                    // UX (re-auditoría post-FIX-1): con el ->using() de
                    // arriba, Filament seguía mostrando su notificación
                    // genérica "Eliminado" aunque un link protegido hubiera
                    // sobrevivido — nada le avisaba al admin que el lote
                    // quedó incompleto. ->successNotificationTitle(null)
                    // apaga esa notificación automática; la de abajo cuenta
                    // cuántos se borraron y cuántos se protegieron.
                    Tables\Actions\DeleteBulkAction::make()
                        ->using(function (\Illuminate\Database\Eloquent\Collection $records): void {
                            $deletedCount = 0;
                            $protectedCount = 0;

                            foreach ($records as $record) {
                                if ($record->delete()) {
                                    $deletedCount++;
                                } else {
                                    $protectedCount++;
                                }
                            }

                            Notification::make()
                                ->title(static::bulkDeleteNotificationTitle($deletedCount, $protectedCount))
                                ->status($protectedCount > 0 ? 'warning' : 'success')
                                ->send();
                        })
                        ->successNotificationTitle(null),
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
            'index' => Pages\ListPaymentLinks::route('/'),
            'create' => Pages\CreatePaymentLink::route('/create'),
            'edit' => Pages\EditPaymentLink::route('/{record}/edit'),
        ];
    }
}
