@php
    /**
     * Cookie consent banner component.
     *
     * Reads $cookieBannerEnabled and $cookieTexts from the parent layout.
     * Uses inline styles throughout — Tailwind purge removes arbitrary classes
     * in this project, so we follow the same pattern as the WhatsApp button.
     */
    $locale = app()->getLocale();

    // Text resolution: admin-configured value → lang fallback
    $bannerText = match ($locale) {
        'en' => \App\Models\Setting::get('cookie_text_en') ?: __('common.cookie_text'),
        'pt' => \App\Models\Setting::get('cookie_text_pt') ?: __('common.cookie_text'),
        default => \App\Models\Setting::get('cookie_text_es') ?: __('common.cookie_text'),
    };

    $privacyUrl = \App\Support\LocalizedPages::url('legal.privacy', $locale);
@endphp

{{--
    x-cloak hides the element until Alpine boots (rule already in <head>: [x-cloak]{display:none!important})
    The component is only rendered when the banner is enabled (checked in layouts/app.blade.php).
--}}
<div
    x-data="{
        show: false,
        root: null,
        init() {
            this.root = this.$el;
            if (!window.lvtConsent.get()) {
                this.show = true;
            }
            // Publica el alto del banner para que los CTAs fijos (WhatsApp, checkout, drawer de filtros) se apoyen encima
            this.$watch('show', () => this.syncHeight());
            new ResizeObserver(() => this.syncHeight()).observe(this.root);
            this.$nextTick(() => this.syncHeight());
        },
        reopen() {
            this.show = true;
            this.$nextTick(() => {
                const first = this.root.querySelector('button');
                if (first) first.focus();
            });
        },
        syncHeight() {
            const h = this.show ? this.root.offsetHeight : 0;
            document.documentElement.style.setProperty('--cookie-banner-h', h + 'px');
        },
        accept() {
            window.lvtConsent.set('granted');
            gtag('consent', 'update', window.lvtConsent.granted);
            window.dispatchEvent(new Event('lvt-consent-granted'));
            this.show = false;
        },
        reject() {
            const wasGranted = window.lvtConsent.get() === 'granted';
            window.lvtConsent.set('denied');
            gtag('consent', 'update', window.lvtConsent.denied);
            this.show = false;
            if (wasGranted) {
                window.lvtConsent.purge();
                if (window._lvtGtmLoaded || window._fbPixelLoaded) location.reload();
            }
        }
    }"
    x-on:lvt-cookie-preferences.window="reopen()"
    x-show="show"
    x-cloak
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-4"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-4"
    id="lvt-cookie-banner"
    role="dialog"
    aria-live="polite"
    aria-label="{{ __('common.cookie_label') }}"
    style="
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 9500;
        background-color: #15474B;
        color: #fff;
        padding: 16px 20px;
        box-shadow: 0 -4px 24px rgba(0,0,0,0.25);
    ">

    <div style="
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
    ">
        {{-- Banner text --}}
        <p style="
            flex: 1 1 300px;
            margin: 0;
            font-size: 0.875rem;
            line-height: 1.5;
            color: #e2e8f0;
        ">
            {{ $bannerText }}
            <a href="{{ $privacyUrl }}"
               style="color: #f0a04b; text-decoration: underline; white-space: nowrap; margin-left: 4px;">
                {{ __('common.cookie_privacy') }}
            </a>
        </p>

        {{-- Action buttons --}}
        <div style="
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            flex-shrink: 0;
        ">
            {{-- Aceptar y Rechazar: mismo estilo, tamano y peso --}}
            <button
                type="button"
                @click="reject()"
                style="
                    padding: 10px 24px;
                    min-height: 44px;
                    border-radius: 6px;
                    border: 2px solid #fff;
                    background-color: #fff;
                    color: #15474B;
                    font-size: 0.875rem;
                    font-weight: 600;
                    cursor: pointer;
                    white-space: nowrap;
                "
                onmouseover="this.style.backgroundColor='#e2e8f0'"
                onmouseout="this.style.backgroundColor='#fff'">
                {{ __('common.cookie_reject') }}
            </button>

            <button
                type="button"
                @click="accept()"
                style="
                    padding: 10px 24px;
                    min-height: 44px;
                    border-radius: 6px;
                    border: 2px solid #fff;
                    background-color: #fff;
                    color: #15474B;
                    font-size: 0.875rem;
                    font-weight: 600;
                    cursor: pointer;
                    white-space: nowrap;
                "
                onmouseover="this.style.backgroundColor='#e2e8f0'"
                onmouseout="this.style.backgroundColor='#fff'">
                {{ __('common.cookie_accept') }}
            </button>
        </div>
    </div>
</div>
