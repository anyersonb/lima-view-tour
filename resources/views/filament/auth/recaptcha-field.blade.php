{{--
    Widget reCAPTCHA para el login del panel (App\Filament\Auth\Login).
    Setea $wire.recaptchaToken (deferred) y lo renueva tras cada intento:
    los tokens de Google son de un solo uso, así que un intento con
    credenciales incorrectas debe regenerarlo antes del siguiente submit.
--}}
@php
    $rcSvc     = app(\App\Services\RecaptchaService::class);
    $rcSiteKey = $rcSvc->siteKey();
    $rcVersion = $rcSvc->version();
@endphp

@if ($rcVersion === 'v2')
    <div
        x-data="{
            widgetId: null,
            render() {
                this.widgetId = window.grecaptcha.render(this.$refs.widget, {
                    sitekey: @js($rcSiteKey),
                    callback: (token) => this.$wire.set('recaptchaToken', token, false),
                    'expired-callback': () => this.$wire.set('recaptchaToken', null, false),
                });
            },
            reset() {
                if (this.widgetId !== null && window.grecaptcha?.reset) {
                    window.grecaptcha.reset(this.widgetId);
                    this.$wire.set('recaptchaToken', null, false);
                }
            },
            init() {
                // Tras cada roundtrip Livewire (intento de login fallido) el
                // token quedó consumido server-side: resetear el checkbox.
                window.Livewire?.hook('commit', ({ succeed }) => succeed(() => this.reset()));

                if (window.grecaptcha?.render) { this.render(); return; }

                window.__lvtsAdminRcOnload = () => this.render();
                if (! document.getElementById('lvts-rc-api')) {
                    const s = document.createElement('script');
                    s.id = 'lvts-rc-api';
                    s.src = 'https://www.google.com/recaptcha/api.js?onload=__lvtsAdminRcOnload&render=explicit';
                    s.async = true;
                    s.defer = true;
                    document.head.appendChild(s);
                }
            },
        }"
    >
        {{-- wire:ignore: el morph de Livewire no debe destruir el iframe de Google --}}
        <div wire:ignore>
            <div x-ref="widget"></div>
        </div>
    </div>
@else
    <div
        x-data="{
            refresh() {
                window.grecaptcha.execute(@js($rcSiteKey), { action: 'admin_login' })
                    .then((token) => this.$wire.set('recaptchaToken', token, false));
            },
            init() {
                const start = () => window.grecaptcha.ready(() => {
                    this.refresh();
                    // Los tokens v3 caducan a los 2 min: renovar antes de eso.
                    setInterval(() => this.refresh(), 100000);
                });

                // Token consumido tras cada intento de login: pedir uno nuevo.
                window.Livewire?.hook('commit', ({ succeed }) => succeed(() =>
                    window.grecaptcha?.execute && this.refresh()
                ));

                if (window.grecaptcha) { start(); return; }

                if (! document.getElementById('lvts-rc-api')) {
                    const s = document.createElement('script');
                    s.id = 'lvts-rc-api';
                    s.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(@js($rcSiteKey));
                    s.async = true;
                    s.onload = start;
                    document.head.appendChild(s);
                } else {
                    document.getElementById('lvts-rc-api').addEventListener('load', start);
                }
            },
        }"
        wire:ignore
        class="hidden"
    ></div>
@endif
