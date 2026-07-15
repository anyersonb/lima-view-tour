{{--
    Herramientas de "Zonas de recogida" (Settings → pestaña Recogida):
    1) Autocompletado de Google Places en el campo "label" de cada fila del Repeater.
    2) Mapa de vista previa con un círculo por zona.

    BLINDADO: objeto x-data INLINE (carga siempre, aunque Filament navegue tipo SPA),
    lee las zonas del DOM (array, no del estado de Livewire), NO se engancha al ciclo
    commit de Livewire, y todo va en try/catch → jamás puede romper el guardado.
--}}
@php
    $mapsKey = \App\Models\Setting::get('google_maps_api_key') ?: config('services.google.maps_api_key');
@endphp

@if (blank($mapsKey))
    <div class="rounded-lg bg-warning-50 ring-1 ring-warning-300 px-4 py-3 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
        Configura la <strong>Google Maps API Key</strong> (pestaña "APIs") para habilitar el autocompletado de lugares y el mapa de vista previa aquí abajo.
        Mientras tanto puedes ingresar <strong>latitud/longitud</strong> a mano: en Google Maps, clic derecho sobre el punto exacto → clic en las coordenadas para copiarlas.
    </div>
@else
    <div
        x-data="{
            map: null, circles: [], markers: [], timer: null,

            init() {
                try {
                    window.__lvtInitPickupAutocomplete = (el) => { try { this.bindAutocomplete(el); } catch (e) {} };
                    this.loadMaps();
                    this.timer = setInterval(() => { try { this.redraw(); } catch (e) {} }, 1500);
                } catch (e) {}
            },

            loadMaps() {
                try {
                    if (window.google && window.google.maps && window.google.maps.places) { this.onReady(); return; }
                    const existing = document.getElementById('lvt-gmaps-sdk');
                    if (existing) { existing.addEventListener('load', () => { try { this.onReady(); } catch (e) {} }); return; }
                    window.__lvtPickupMapsReady = () => { try { this.onReady(); } catch (e) {} };
                    const s = document.createElement('script');
                    s.id = 'lvt-gmaps-sdk';
                    s.src = 'https://maps.googleapis.com/maps/api/js?key={{ $mapsKey }}&libraries=places&loading=async&callback=__lvtPickupMapsReady';
                    s.async = true; s.defer = true;
                    document.head.appendChild(s);
                } catch (e) {}
            },

            onReady() {
                try {
                    document.querySelectorAll('[data-pickup-place-input]').forEach((el) => this.bindAutocomplete(el));
                    this.initMap();
                    this.redraw();
                } catch (e) {}
            },

            initMap() {
                try {
                    if (this.map || ! this.$refs.canvas || ! (window.google && window.google.maps)) return;
                    this.map = new google.maps.Map(this.$refs.canvas, { center: { lat: -12.09, lng: -77.03 }, zoom: 11 });
                } catch (e) {}
            },

            bindAutocomplete(el) {
                try {
                    if (! (window.google && window.google.maps && window.google.maps.places) || ! el || el.dataset.pickupBound) return;
                    el.dataset.pickupBound = '1';
                    const ac = new google.maps.places.Autocomplete(el, { fields: ['geometry', 'name'] });
                    ac.addListener('place_changed', () => {
                        try {
                            const place = ac.getPlace();
                            if (! place.geometry || ! place.geometry.location) return;
                            const item = el.closest('.fi-fo-repeater-item');
                            if (! item) return;
                            const latInput = item.querySelector('[data-pickup-lat]');
                            const lngInput = item.querySelector('[data-pickup-lng]');
                            if (latInput) { latInput.value = place.geometry.location.lat(); latInput.dispatchEvent(new Event('input', { bubbles: true })); }
                            if (lngInput) { lngInput.value = place.geometry.location.lng(); lngInput.dispatchEvent(new Event('input', { bubbles: true })); }
                            this.redraw();
                        } catch (e) {}
                    });
                } catch (e) {}
            },

            readZones() {
                const zones = [];
                try {
                    document.querySelectorAll('[data-pickup-lat]').forEach((latEl) => {
                        const item = latEl.closest('.fi-fo-repeater-item');
                        if (! item) return;
                        const lat = parseFloat(latEl.value);
                        const lngEl = item.querySelector('[data-pickup-lng]');
                        const lng = parseFloat(lngEl ? lngEl.value : NaN);
                        const labelEl = item.querySelector('[data-pickup-place-input]');
                        if (! isNaN(lat) && ! isNaN(lng)) zones.push({ lat, lng, label: labelEl ? labelEl.value : '' });
                    });
                } catch (e) {}
                return zones;
            },

            redraw() {
                try {
                    if (! this.map || ! (window.google && window.google.maps)) return;
                    this.circles.forEach((c) => c.setMap(null));
                    this.markers.forEach((m) => m.setMap(null));
                    this.circles = []; this.markers = [];
                    const zones = this.readZones();
                    const bounds = new google.maps.LatLngBounds();
                    let any = false;
                    zones.forEach((z) => {
                        const center = { lat: z.lat, lng: z.lng };
                        any = true; bounds.extend(center);
                        this.markers.push(new google.maps.Marker({ position: center, map: this.map, title: z.label || '' }));
                        this.circles.push(new google.maps.Circle({
                            map: this.map, center, radius: 2000,
                            strokeColor: '#f97316', strokeOpacity: 0.8, strokeWeight: 1.5,
                            fillColor: '#f97316', fillOpacity: 0.12,
                        }));
                    });
                    if (any) this.map.fitBounds(bounds);
                } catch (e) {}
            },
        }"
        class="space-y-2"
    >
        <p class="text-xs text-gray-500 dark:text-gray-400">Vista previa: el mapa se actualiza al elegir un lugar con el autocompletado o al escribir coordenadas.</p>
        <div wire:ignore x-ref="canvas" class="w-full rounded-lg ring-1 ring-gray-200 dark:ring-white/10" style="height:16rem"></div>
    </div>
@endif
