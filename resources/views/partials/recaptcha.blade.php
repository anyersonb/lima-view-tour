{{--
    Partial: partials/recaptcha.blade.php

    Usage in a <form>:
        @include('partials.recaptcha', ['recaptchaAction' => 'contact'])

    Props:
        $recaptchaAction  (string) – v3 action name; ignored for v2. Default: 'submit'.
        $recaptchaFormId  (string) – unique ID of the parent <form> element;
                                     used by v3 JS to attach the token before submit.
                                     Default: 'recaptcha-form-' . uniqid().
--}}
@php
    /** @var \App\Services\RecaptchaService $recaptchaSvc */
    $recaptchaSvc    = app(\App\Services\RecaptchaService::class);
    $rcEnabled       = $recaptchaSvc->enabled();
    $rcSiteKey       = $recaptchaSvc->siteKey();
    $rcVersion       = $recaptchaSvc->version();
    $recaptchaAction = $recaptchaAction ?? 'submit';
    $recaptchaFormId = $recaptchaFormId ?? ('rcform-' . uniqid());
@endphp

@if ($rcEnabled && $rcSiteKey)

    @if ($rcVersion === 'v2')
        {{-- ── v2 widget (visible checkbox) ─────────────────────────────── --}}
        <div class="g-recaptcha mt-4" data-sitekey="{{ $rcSiteKey }}"></div>

        @push('head')
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        @endpush

    @else
        {{-- ── v3 (invisible; token injected into hidden field before submit) --}}
        <input type="hidden" name="recaptcha_token" id="recaptcha_token_{{ $recaptchaFormId }}">

        @push('scripts')
        <script>
        (function () {
            // Load reCAPTCHA v3 only once per page
            if (!window.__recaptchaV3Loaded) {
                window.__recaptchaV3Loaded = true;
                var s = document.createElement('script');
                s.src = 'https://www.google.com/recaptcha/api.js?render={{ $rcSiteKey }}';
                s.async = true;
                document.head.appendChild(s);
            }

            // Intercept the parent form's submit to inject the token first
            document.addEventListener('DOMContentLoaded', function () {
                var formId   = '{{ $recaptchaFormId }}';
                var inputId  = 'recaptcha_token_' + formId;
                var siteKey  = '{{ $rcSiteKey }}';
                var action   = '{{ $recaptchaAction }}';

                // Find form that contains our hidden input
                var hiddenInput = document.getElementById(inputId);
                if (!hiddenInput) return;

                var form = hiddenInput.closest('form');
                if (!form) return;

                form.addEventListener('submit', function (e) {
                    // If token already set (re-submit guard) let it through
                    if (hiddenInput.value) return;

                    e.preventDefault();

                    if (typeof grecaptcha === 'undefined' || !grecaptcha.execute) {
                        // reCAPTCHA not loaded yet — allow submit without token
                        form.submit();
                        return;
                    }

                    grecaptcha.ready(function () {
                        grecaptcha.execute(siteKey, { action: action })
                            .then(function (token) {
                                hiddenInput.value = token;
                                form.submit();
                            })
                            .catch(function () {
                                // Execution failed — allow submit anyway
                                form.submit();
                            });
                    });
                });
            });
        }());
        </script>
        @endpush
    @endif

@endif
