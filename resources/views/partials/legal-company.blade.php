{{--
    Datos del proveedor para las páginas legales (Términos, Privacidad,
    Devoluciones). Lee Configuración → Datos legales vía LegalInfo; las líneas
    vacías (razón social / RUC / dirección sin cargar) simplemente no se pintan.
--}}
@php
    $companyLines = \App\Support\LegalInfo::lines();
@endphp
<section class="mt-12 pt-8 border-t border-gray-200" aria-labelledby="legal-company-title">
    <h2 id="legal-company-title" class="font-display text-xl md:text-2xl text-teal-700 mb-3">
        {{ __('legal.company_heading') }}
    </h2>
    <dl class="text-gray-700 leading-relaxed space-y-1">
        <div><dt class="inline font-semibold">Lima View Tours</dt></div>
        @foreach ($companyLines as $label => $value)
            <div><dt class="inline font-semibold">{{ $label }}:</dt> <dd class="inline">{{ $value }}</dd></div>
        @endforeach
        <div>
            <dt class="inline font-semibold">{{ __('legal.company_phone') }}:</dt>
            <dd class="inline"><a href="tel:{{ \App\Support\LegalInfo::phoneTel() }}" class="text-teal-700 underline">{{ \App\Support\LegalInfo::phone() }}</a></dd>
        </div>
        <div>
            <dt class="inline font-semibold">{{ __('legal.company_email') }}:</dt>
            <dd class="inline"><a href="mailto:{{ \App\Support\LegalInfo::email() }}" class="text-teal-700 underline">{{ \App\Support\LegalInfo::email() }}</a></dd>
        </div>
    </dl>
    <ul class="mt-5 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:gap-x-6">
        <li><a href="{{ \App\Support\LocalizedPages::url('legal.refund', $locale) }}" class="inline-flex items-center min-h-[44px] text-teal-700 hover:text-orange-700 font-medium underline underline-offset-2">{{ __('legal.link_refund') }}</a></li>
        <li><a href="{{ \App\Support\LocalizedPages::url('complaints', $locale) }}" class="inline-flex items-center min-h-[44px] text-teal-700 hover:text-orange-700 font-medium underline underline-offset-2">{{ __('legal.link_complaints') }}</a></li>
    </ul>
</section>
