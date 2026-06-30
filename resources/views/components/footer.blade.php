@php
    $locale = app()->getLocale();
    $contactPhone = \App\Models\Setting::get('contact_phone', '+51 925 886 725');
@endphp
<footer class="site-footer" role="contentinfo">
    {{-- Newsletter band --}}
    <section aria-labelledby="newsletter-title" class="container mx-auto px-5 lg:px-10 py-14 grid gap-10 lg:grid-cols-2 items-center">
        <div>
            <p class="text-sm text-white/75">{{ __('footer.newsletter_eyebrow') }}</p>
            <h2 id="newsletter-title" class="mt-3 text-white font-display text-2xl md:text-3xl lg:text-4xl leading-snug">
                {{ __('footer.newsletter_title') }}
            </h2>
        </div>

        @if (session('newsletter_success'))
            <p class="mb-3 rounded-2xl bg-emerald-500/20 border border-emerald-300/40 text-white text-sm px-4 py-3" role="status">{{ session('newsletter_success') }}</p>
        @elseif ($errors->has('email') || $errors->has('name'))
            <p class="mb-3 rounded-2xl bg-red-500/20 border border-red-300/40 text-white text-sm px-4 py-3" role="alert">{{ $errors->first('email') ?: $errors->first('name') }}</p>
        @endif
        <form action="{{ route('newsletter.subscribe') }}" method="post" id="form-newsletter" class="space-y-3">
            @csrf
            {{-- Honeypot: must remain empty; bots fill it automatically --}}
            <input type="text" name="website" tabindex="-1" autocomplete="off"
                   style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;"
                   aria-hidden="true">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="sr-only">{{ __('footer.newsletter_name') }}</span>
                    <input type="text" name="name" required
                           placeholder="{{ __('footer.newsletter_name') }}"
                           class="w-full rounded-pill bg-white/10 border border-white/25 text-white placeholder-white/50 px-5 py-3.5 text-sm focus:border-orange-400 focus:ring-orange-400 focus:ring-1 focus:outline-none">
                </label>
                <label class="block">
                    <span class="sr-only">{{ __('footer.newsletter_email') }}</span>
                    <input type="email" name="email" required
                           placeholder="{{ __('footer.newsletter_email') }}"
                           class="w-full rounded-pill bg-white/10 border border-white/25 text-white placeholder-white/50 px-5 py-3.5 text-sm focus:border-orange-400 focus:ring-orange-400 focus:ring-1 focus:outline-none">
                </label>
            </div>
            @include('partials.recaptcha', ['recaptchaAction' => 'newsletter', 'recaptchaFormId' => 'form-newsletter'])
            <button type="submit" class="btn--primary btn--block !text-sm !py-4 !rounded-pill">
                {{ __('footer.newsletter_submit') }}
            </button>
        </form>
    </section>

    {{-- Main footer grid --}}
    <div class="border-t border-white/10">
        <div class="container mx-auto py-14 grid gap-10 md:grid-cols-2 lg:grid-cols-4">
            <section aria-labelledby="footer-brand">
                <a href="{{ route('home', ['locale' => $locale]) }}" aria-label="Lima View Tours — Inicio" class="inline-flex items-center gap-3">
                    <img src="{{ asset('assets/logos/logo.png') }}" alt="Lima View Tours" class="h-14 w-auto">
                </a>
                <p class="mt-4 text-sm leading-relaxed text-white/75">
                    {{ \App\Models\Setting::get('footer_about_' . $locale) ?: __('footer.brand_description') }}
                </p>
            </section>

            <nav aria-labelledby="footer-links">
                <h3 id="footer-links" class="site-footer__heading">{{ __('footer.links') }}</h3>
                <ul class="mt-4 space-y-2.5">
                    <li><a href="{{ route('home', ['locale' => $locale]) }}" class="site-footer__link">{{ __('nav.home') }}</a></li>
                    <li><a href="{{ url('/' . $locale . '/nosotros') }}" class="site-footer__link">{{ __('footer.about_short') }}</a></li>
                    <li><a href="{{ route('tours.index', ['locale' => $locale]) }}#lima" class="site-footer__link">{{ __('nav.tours_lima') }}</a></li>
                    <li><a href="{{ route('tours.index', ['locale' => $locale]) }}#ica" class="site-footer__link">{{ __('nav.tours_ica') }}</a></li>
                    <li><a href="{{ route('tours.index', ['locale' => $locale]) }}#cusco" class="site-footer__link">{{ __('nav.tours_cusco') }}</a></li>
                    <li><a href="{{ route('contact', ['locale' => $locale]) }}" class="site-footer__link">{{ __('nav.contact') }}</a></li>
                    <li><a href="{{ route('legal.terms', ['locale' => $locale]) }}" class="site-footer__link">{{ __('footer.terms') }}</a></li>
                    <li><a href="{{ route('legal.privacy', ['locale' => $locale]) }}" class="site-footer__link">{{ __('footer.privacy') }}</a></li>
                </ul>
            </nav>

            <section aria-labelledby="footer-locate">
                <h3 id="footer-locate" class="site-footer__heading">{{ __('footer.locate_us') }}</h3>
                <address class="not-italic mt-4 space-y-3 text-sm">
                    <p class="flex items-start gap-2">
                        <svg class="w-4 h-4 mt-0.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                        <span>{{ __('footer.address') }}</span>
                    </p>
                    <p class="flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                        <a href="tel:{{ str_replace([' ', '+'], '', $contactPhone) }}" class="hover:text-orange-400">{{ $contactPhone }}</a>
                    </p>
                    <p class="flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ __('footer.hours') }}</span>
                    </p>
                </address>
            </section>

            <section aria-labelledby="footer-follow">
                <h3 id="footer-follow" class="site-footer__heading">{{ __('footer.follow_us') }}</h3>
                @php
                    $sIg = \App\Models\Setting::get('social_instagram');
                    $sFb = \App\Models\Setting::get('social_facebook');
                    $sTk = \App\Models\Setting::get('social_tiktok');
                    $sYt = \App\Models\Setting::get('social_youtube');
                    $norm = fn ($u) => $u ? (\Illuminate\Support\Str::startsWith($u, ['http://', 'https://']) ? $u : 'https://' . ltrim($u, '/')) : null;
                @endphp
                <ul class="mt-4 flex items-center gap-3">
                    @if ($u = $norm($sIg))<li><a href="{{ $u }}" target="_blank" rel="noopener" aria-label="Instagram" class="site-footer__link"><svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.2c3.2 0 3.6 0 4.8.1 1.2.1 1.8.2 2.2.4.6.2 1 .5 1.4.9.4.4.7.9.9 1.4.2.5.4 1.1.4 2.2.1 1.2.1 1.6.1 4.8s0 3.6-.1 4.8c-.1 1.2-.2 1.8-.4 2.2-.2.6-.5 1-.9 1.4-.4.4-.9.7-1.4.9-.5.2-1.1.4-2.2.4-1.2.1-1.6.1-4.8.1s-3.6 0-4.8-.1c-1.2-.1-1.8-.2-2.2-.4-.6-.2-1-.5-1.4-.9-.4-.4-.7-.9-.9-1.4-.2-.5-.4-1.1-.4-2.2-.1-1.2-.1-1.6-.1-4.8s0-3.6.1-4.8c.1-1.2.2-1.8.4-2.2.2-.6.5-1 .9-1.4.4-.4.9-.7 1.4-.9.5-.2 1.1-.4 2.2-.4 1.2-.1 1.6-.1 4.8-.1zm0 5.5a4.3 4.3 0 100 8.6 4.3 4.3 0 000-8.6zm0 7.1a2.8 2.8 0 110-5.6 2.8 2.8 0 010 5.6zm5.5-7.3a1 1 0 11-2 0 1 1 0 012 0z"/></svg></a></li>@endif
                    @if ($u = $norm($sFb))<li><a href="{{ $u }}" target="_blank" rel="noopener" aria-label="Facebook" class="site-footer__link"><svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12a10 10 0 10-11.6 9.9v-7H8v-2.9h2.4V9.8c0-2.4 1.4-3.7 3.6-3.7 1 0 2.1.2 2.1.2v2.3h-1.2c-1.2 0-1.5.7-1.5 1.5v1.8h2.6l-.4 2.9h-2.2v7A10 10 0 0022 12z"/></svg></a></li>@endif
                    @if ($u = $norm($sTk))<li><a href="{{ $u }}" target="_blank" rel="noopener" aria-label="TikTok" class="site-footer__link"><svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M19.6 6.7a4.8 4.8 0 01-2.7-1.7 4.8 4.8 0 01-1-2.7h-3.4v13.4a2.7 2.7 0 11-2.7-2.7c.3 0 .6 0 .9.1V9.6a6.1 6.1 0 00-.9-.1 6.1 6.1 0 106.1 6.1V9.4a8.2 8.2 0 003.7.9V6.7z"/></svg></a></li>@endif
                    @if ($u = $norm($sYt))<li><a href="{{ $u }}" target="_blank" rel="noopener" aria-label="YouTube" class="site-footer__link"><svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M23 7s-.2-1.6-.9-2.3c-.9-.9-1.8-.9-2.3-1C16.4 3.5 12 3.5 12 3.5s-4.4 0-7.8.3c-.5 0-1.4 0-2.3 1C1.2 5.4 1 7 1 7S.7 8.9.7 10.8v1.7C.7 14.4 1 16.3 1 16.3s.2 1.6.9 2.3c.9 1 2.1.9 2.7 1 1.9.2 8.4.3 8.4.3s4.4 0 7.8-.3c.5 0 1.4 0 2.3-1 .7-.7.9-2.3.9-2.3s.3-1.9.3-3.8v-1.7C23.3 8.9 23 7 23 7zM9.7 14.7v-6l5.7 3-5.7 3z"/></svg></a></li>@endif
                </ul>

                <h3 class="site-footer__heading mt-6">{{ __('footer.methods_of_payment') }}</h3>
                <ul class="mt-3 flex items-center gap-2 flex-wrap" aria-label="Métodos de pago aceptados">
                    {{-- Visa --}}
                    <li class="bg-white rounded px-2 py-1">
                        <svg class="h-4 w-auto" aria-label="Visa" role="img" viewBox="0 0 60 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <text x="0" y="16" font-family="Arial" font-size="14" font-weight="bold" fill="#1A1F71">VISA</text>
                        </svg>
                    </li>
                    {{-- PayPal --}}
                    <li class="bg-white rounded px-2 py-1">
                        <svg class="h-4 w-auto" aria-label="PayPal" role="img" viewBox="0 0 60 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <text x="0" y="15" font-family="Arial" font-size="11" font-weight="bold" fill="#003087">Pay</text>
                            <text x="22" y="15" font-family="Arial" font-size="11" font-weight="bold" fill="#009cde">Pal</text>
                        </svg>
                    </li>
                    {{-- Mastercard --}}
                    <li class="bg-white rounded px-2 py-1 flex items-center gap-0.5">
                        <span class="w-4 h-4 rounded-full bg-[#EB001B] inline-block" aria-hidden="true"></span>
                        <span class="w-4 h-4 rounded-full bg-[#F79E1B] inline-block -ml-2" aria-hidden="true"></span>
                        <span class="sr-only">Mastercard</span>
                    </li>
                    {{-- American Express --}}
                    <li class="bg-[#2E77BC] text-white rounded px-2 py-1">
                        <span class="text-[9px] font-bold tracking-tight leading-none">AMEX</span>
                    </li>
                    {{-- Culqi/Niubiz --}}
                    <li class="bg-white rounded px-2 py-1">
                        <span class="text-[9px] font-bold text-teal-700 tracking-tight leading-none">CULQI</span>
                    </li>
                </ul>
            </section>
        </div>
    </div>

    {{-- Copyright --}}
    <div class="border-t border-white/10">
        <div class="container mx-auto py-4 text-center text-xs text-white/60">
            <p>&copy; <time datetime="{{ now()->year }}">{{ now()->year }}</time> Lima View Tours – {{ __('common.rights_reserved') }}.</p>
        </div>
    </div>
</footer>
