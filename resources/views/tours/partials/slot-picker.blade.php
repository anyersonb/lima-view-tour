{{--
    Horario de salida + aviso de "mismo día" (funcional, sin diseño propio).
    Todo el dato sale de BookingCalendar::availability() (hora de Lima, calculado
    en servidor): el navegador solo compara strings 'Y-m-d' y no calcula fechas.
    Estado en Alpine.store('booking'): slot, slotList(), label(), sameDayNow().
    Se muestra el selector solo si ese día hay MÁS de un horario; con uno solo
    el store lo asigna solo (ensureSlot). El campo que viaja al servidor es
    <input name="departure_slot"> de #form-reservar.
--}}
@if (! empty($bookingAvailability['slots']) || ! empty($bookingAvailability['same_day']))
<div data-slot-picker x-data x-effect="$store.booking.ensureSlot()">
    <div x-show="$store.booking.slotList().length > 1" x-cloak style="margin-top:8px">
        <label class="block text-[11px] font-semibold text-teal-800 mb-1" for="slot-select-{{ $idSuffix ?? 'x' }}">{{ __('booking.slot_label') }}</label>
        <select id="slot-select-{{ $idSuffix ?? 'x' }}" x-model="$store.booking.slot" data-booking-slot
                class="w-full rounded-xl border border-teal-800/20 bg-cream-100 px-3 py-2.5 text-sm text-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-700 focus:border-transparent">
            <template x-for="s in $store.booking.slotList()" :key="s">
                <option :value="s" x-text="$store.booking.label(s)"></option>
            </template>
        </select>
    </div>
    <p x-show="$store.booking.sameDayNow()" x-cloak class="mt-1.5 text-[11px] leading-snug text-teal-800/75" data-same-day-notice>{{ __('booking.same_day_notice', ['hours' => $bookingAvailability['min_hours'] ?? 4]) }}</p>
    @error('departure_slot')
        <p class="mt-1.5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs font-medium px-3 py-2" role="alert" data-date-server-error>{{ $message }}</p>
    @enderror
</div>
@endif
