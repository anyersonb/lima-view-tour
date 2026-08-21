<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

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
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todas'),

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

            'today' => Tab::make('Salidas de hoy')
                ->modifyQueryUsing(fn (Builder $query) => $query->travelingBetween(
                    Date::today()->toDateString(),
                    Date::today()->toDateString(),
                ))
                ->badge(fn () => Booking::query()->travelingBetween(
                    Date::today()->toDateString(),
                    Date::today()->toDateString(),
                )->count()),

            'this_week' => Tab::make('Salidas de esta semana')
                ->modifyQueryUsing(fn (Builder $query) => $query->travelingBetween(
                    Date::today()->startOfWeek()->toDateString(),
                    Date::today()->endOfWeek()->toDateString(),
                ))
                ->badge(fn () => Booking::query()->travelingBetween(
                    Date::today()->startOfWeek()->toDateString(),
                    Date::today()->endOfWeek()->toDateString(),
                )->count()),
        ];
    }
}
