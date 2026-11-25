{{--
    Horario de inicio de la ficha (diseño de la maqueta: botones "9:00 a. m. · Mañana").
    Todo el dato sale de BookingCalendar::availability() (hora de Lima, calculado en servidor):
    el navegador solo compara strings 'Y-m-d' y no calcula fechas.
    Variables de la vista padre: $slotsAll (HH:MM), $slotLabels, $slotPart (closure).
    - 2 o más horarios: un botón por horario; si el día elegido es HOY, solo se habilitan los de today_slots.
    - 1 horario: se muestra fijo (no hay nada que elegir) y el store lo asigna solo.
    Estado en Alpine.store('booking'): slot, slotList(), slotEnabled(), ensureSlot().
    El campo que viaja al servidor es <input name="departure_slot"> de #form-reservar.
--}}
@if (! empty($slotsAll))
@php
    // Espacio no separable: "7:00 p. m." no se parte en dos líneas.
    $nb = fn (string $t) => str_replace(' ', "\u{00A0}", $t);
@endphp
<div class="fi-field" data-slot-picker x-data x-effect="$store.booking.ensureSlot()">
    <span class="fi-field__label" id="fi-slot-label">{{ __('ficha.slot_label') }}</span>
    @if (count($slotsAll) === 1)
        @php $only = $slotsAll[0]; @endphp
        <div class="fi-slots fi-slots--fixed" role="group" aria-labelledby="fi-slot-label">
            <div class="fi-slot fi-slot--fixed" style="background:#e3f0e5;border:2px solid #247a55;color:#0c583d">
                <span class="fi-slot__time">{{ $nb($slotLabels[$only] ?? $only) }}</span>
                <small>{{ $slotPart($only) }}</small>
            </div>
        </div>
    @else
        <div class="fi-slots" role="group" aria-labelledby="fi-slot-label">
            @foreach ($slotsAll as $s)
                <button type="button" class="fi-slot" data-booking-slot="{{ $s }}"
                        :aria-pressed="$store.booking.slot === '{{ $s }}' ? 'true' : 'false'"
                        :disabled="! $store.booking.slotEnabled('{{ $s }}')"
                        @click="$store.booking.slot = '{{ $s }}'">
                    <span class="fi-slot__time">{{ $nb($slotLabels[$s] ?? $s) }}</span>
                    <small>{{ $slotPart($s) }}</small>
                </button>
            @endforeach
        </div>
    @endif
    @error('departure_slot')
        <p class="fi-error" role="alert" data-date-server-error>{{ $message }}</p>
    @enderror
</div>
@endif
