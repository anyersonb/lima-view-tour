<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use App\Support\BookingCalendar;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Accesos rápidos de un clic para lo que el admin revisa a diario.
     * Los contadores están habilitados: a ~20k reservas cada uno corre en
     * pocos milisegundos gracias a los índices agregados en
     * 2026_08_19_000000_add_filter_indexes_to_bookings_table.php (medido:
     * ~3ms por conteo indexado). Si el volumen creciera muy por encima de
     * eso, lo primero a revisar es quitar estos badges antes que tocar
     * los índices.
     */
    public function getDefaultActiveTab(): string | int | null
    {
        return 'tomorrow';
    }

    public function getTabs(): array
    {
        return [
            'tomorrow' => Tab::make('Mañana')
                ->modifyQueryUsing(fn (Builder $query) => $query->travelingBetween(
                    BookingCalendar::today()->addDay()->toDateString(),
                    BookingCalendar::today()->addDay()->toDateString(),
                ))
                ->badge(fn () => Booking::query()->travelingBetween(
                    BookingCalendar::today()->addDay()->toDateString(),
                    BookingCalendar::today()->addDay()->toDateString(),
                )->count()),

            'all' => Tab::make('Todas')
                ->badge(fn () => Booking::query()->count()),

            'paid' => Tab::make('Pagadas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_status', 'paid'))
                ->badge(fn () => Booking::query()->where('payment_status', 'paid')->count()),

            'pending_payment' => Tab::make('Pago pendiente')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_status', 'pending'))
                ->badge(fn () => Booking::query()->where('payment_status', 'pending')->count()),

            'failed' => Tab::make('Fallidas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_status', 'failed'))
                ->badge(fn () => Booking::query()->where('payment_status', 'failed')->count()),

            'at_risk' => Tab::make('Pago en riesgo')
                ->modifyQueryUsing(fn (Builder $query) => $query->paymentAtRisk())
                ->badge(fn () => Booking::query()->paymentAtRisk()->count())
                ->badgeColor('danger'),

            // `Date::today()` (facade de Carbon) cae en `app.timezone`, que es
            // UTC (ver config/booking.php). Entre las 19:00 y la medianoche
            // de Lima el servidor ya está en el día siguiente: este tab
            // mostraba las salidas de "mañana en Lima" bajo la etiqueta
            // "Salidas de hoy". BookingCalendar::today() es la MISMA fuente
            // de verdad que usa el checkout para decidir qué día es hoy.
            'today' => Tab::make('Salidas de hoy')
                ->modifyQueryUsing(fn (Builder $query) => $query->travelingBetween(
                    BookingCalendar::today()->toDateString(),
                    BookingCalendar::today()->toDateString(),
                ))
                ->badge(fn () => Booking::query()->travelingBetween(
                    BookingCalendar::today()->toDateString(),
                    BookingCalendar::today()->toDateString(),
                )->count()),

            // B5: mismo bug que tenía 'today' — usaba Date::today() (UTC) para
            // calcular el inicio/fin de semana. Corregido con BookingCalendar::today(),
            // la misma fuente de verdad que el resto de tabs de este archivo.
            'this_week' => Tab::make('Salidas de esta semana')
                ->modifyQueryUsing(fn (Builder $query) => $query->travelingBetween(
                    BookingCalendar::today()->startOfWeek()->toDateString(),
                    BookingCalendar::today()->endOfWeek()->toDateString(),
                ))
                ->badge(fn () => Booking::query()->travelingBetween(
                    BookingCalendar::today()->startOfWeek()->toDateString(),
                    BookingCalendar::today()->endOfWeek()->toDateString(),
                )->count()),
        ];
    }
}
